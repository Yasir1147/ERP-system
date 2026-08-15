<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Attendance\ChatEmployeeMatcher;
use App\Services\Attendance\WhatsAppAttendanceParser;
use App\Services\Excel\FormatsWorkbook;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Turns a WhatsApp chat export into a reviewable attendance sheet.
 *
 * Nothing is written to the database. The output is a workbook a person
 * checks and corrects, which is then fed to the importer.
 */
class ParseChatAttendance extends Command
{
    use FormatsWorkbook;

    protected $signature = 'attendance:parse-chat
        {file : Path to the exported WhatsApp .txt}
        {--match= : Only take blocks whose heading contains this, e.g. opulence}
        {--project= : Project name to write in the sheet}
        {--type= : Employee type to pre-fill code suggestions from, if the database is reachable}
        {--from= : Skip rows before this date, Y-m-d}
        {--to= : Skip rows after this date, Y-m-d}
        {--skip-supply : Leave out days marked as supplied to another company}
        {--out= : Where to write the workbook}';

    protected $description = 'Build a reviewable attendance sheet from a WhatsApp chat export';

    public function handle(WhatsAppAttendanceParser $parser): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $rows = $parser->parse((string) file_get_contents($file));

        if ($match = $this->option('match')) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row) => $row['project'] !== null
                    && str_contains(mb_strtolower($row['project']), mb_strtolower($match)),
            ));
        }

        $rows = $this->applyFilters($rows);

        if ($rows === []) {
            $this->warn('No attendance rows matched.');

            return self::SUCCESS;
        }

        $rows = $this->addSuggestions($rows);

        $out = $this->option('out') ?: storage_path('app/chat-attendance-'.now()->format('Ymd-His').'.xlsx');

        $this->write($rows, $out);
        $this->report($rows, $out);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $rows): array
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $skipSupply = (bool) $this->option('skip-supply');

        return array_values(array_filter($rows, function (array $row) use ($from, $to, $skipSupply) {
            if ($row['date'] === null) {
                return false;
            }

            if ($from && $row['date'] < $from) {
                return false;
            }

            if ($to && $row['date'] > $to) {
                return false;
            }

            return ! ($skipSupply && in_array('supply', $row['flags'], true));
        }));
    }

    /**
     * Pre-fills a suggested employee code where the database is reachable.
     *
     * The sheet is designed to be filled on whichever machine holds the real
     * employee records, so a missing database is not an error here.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function addSuggestions(array $rows): array
    {
        $matcher = null;

        if ($type = $this->option('type')) {
            try {
                $matcher = new ChatEmployeeMatcher(Employee::query()->where('type', $type)->get());
            } catch (\Throwable) {
                $this->warn('Database not reachable; the Employee Code column is left for you to fill.');
            }
        }

        return array_map(function (array $row) use ($matcher) {
            $result = $matcher?->match($row['sourceName']);

            return $row + [
                'matchStatus' => $result['status'] ?? 'unmapped',
                'employeeCode' => $result['employee']['code'] ?? null,
                'employeeName' => $result['employee']['name'] ?? null,
                'candidates' => implode(' | ', $result['candidates'] ?? []),
            ];
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function write(array $rows, string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $project = (string) ($this->option('project') ?? '');

        $this->writeAttendanceSheet($spreadsheet->getActiveSheet(), $rows, $project);
        $this->writeMapSheet($spreadsheet->createSheet(), $rows);

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * Every attendance row, keyed on the name exactly as the chat wrote it.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeAttendanceSheet(Worksheet $sheet, array $rows, string $project): void
    {
        $sheet->setTitle('Attendance');

        $headerRow = 1;

        $this->writeTableHeadings($sheet, [
            'Date',
            'Chat Name',
            'Project',
            'Status',
            'Day',
            'Overtime Hours',
            'Overtime Project',
            'Note',
            'Chat Heading',
            'Flags',
            'Sent By',
        ], $headerRow);

        $row = $headerRow + 1;

        foreach ($rows as $entry) {
            $sheet->setCellValue('A'.$row, $entry['date']);
            $sheet->setCellValue('B'.$row, $entry['sourceName']);
            $sheet->setCellValue('C'.$row, $project !== '' ? $project : $entry['project']);
            $sheet->setCellValue('D'.$row, ucfirst($entry['status']));
            $sheet->setCellValue('E'.$row, $entry['attendanceFraction'] == 0.5 ? 'Half' : 'Full');
            $sheet->setCellValue('F'.$row, '');
            $sheet->setCellValue('G'.$row, '');
            $sheet->setCellValue('H'.$row, $entry['note']);
            $sheet->setCellValue('I'.$row, $entry['project']);
            $sheet->setCellValue('J'.$row, implode(', ', $entry['flags']));
            $sheet->setCellValue('K'.$row, $entry['sender']);

            // Rows carrying a flag are the ones a person must look at.
            if ($entry['flags'] !== []) {
                $sheet->getStyle('A'.$row.':K'.$row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::WARNING_FILL);
            }

            $row++;
        }

        $lastRow = $row - 1;

        $sheet->setAutoFilter('A'.$headerRow.':K'.$lastRow);
        $this->drawGrid($sheet, 'A'.$headerRow.':K'.$lastRow);
        $sheet->freezePane('A'.($headerRow + 1));

        $this->applyColumnWidths($sheet, [
            'A' => 12, 'B' => 20, 'C' => 22, 'D' => 10, 'E' => 8,
            'F' => 14, 'G' => 18, 'H' => 24, 'I' => 26, 'J' => 14, 'K' => 20,
        ]);
    }

    /**
     * The unique chat names, for a person to map to employee codes once.
     *
     * Filling a code per distinct name is roughly fifteen rows instead of
     * one per attendance line, and a name can then never be mapped two
     * different ways in the same file.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeMapSheet(Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Employee Map');

        $sheet->setCellValue('A1', 'Fill Employee Code for each name below, then import this file.');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $headerRow = 3;

        $this->writeTableHeadings($sheet, [
            'Chat Name',
            'Employee Code',
            'Suggested Employee',
            'Match',
            'Days In File',
        ], $headerRow);

        $names = [];

        foreach ($rows as $entry) {
            $key = mb_strtolower($entry['sourceName']);

            $names[$key] ??= [
                'name' => $entry['sourceName'],
                'code' => $entry['employeeCode'],
                'suggested' => $entry['employeeName'],
                'match' => $entry['matchStatus'],
                'candidates' => $entry['candidates'],
                'count' => 0,
            ];

            $names[$key]['count']++;
        }

        ksort($names);

        $row = $headerRow + 1;

        foreach ($names as $entry) {
            $sheet->setCellValue('A'.$row, $entry['name']);
            $sheet->setCellValue('B'.$row, $entry['code']);
            $sheet->setCellValue('C'.$row, $entry['suggested'] ?: $entry['candidates']);
            $sheet->setCellValue('D'.$row, $entry['match']);
            $sheet->setCellValue('E'.$row, $entry['count']);

            $sheet->getStyle('B'.$row)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);

            if ($entry['code'] === null) {
                $sheet->getStyle('A'.$row.':E'.$row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::WARNING_FILL);
            }

            $row++;
        }

        $this->drawGrid($sheet, 'A'.$headerRow.':E'.($row - 1));
        $this->applyColumnWidths($sheet, ['A' => 22, 'B' => 16, 'C' => 30, 'D' => 14, 'E' => 14]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function report(array $rows, string $path): void
    {
        $names = array_unique(array_map(fn ($r) => mb_strtolower($r['sourceName']), $rows));
        $mapped = count(array_filter($rows, fn ($r) => $r['employeeCode'] !== null));
        $flagged = count(array_filter($rows, fn ($r) => $r['flags'] !== []));

        $dates = array_values(array_filter(array_column($rows, 'date')));
        sort($dates);

        $this->newLine();
        $this->info('Written: '.$path);
        $this->newLine();

        $this->table(['', 'Count'], [
            ['Attendance rows', count($rows)],
            ['Distinct dates', count(array_unique($dates))],
            ['Distinct names to map', count($names)],
            ['Rows with a suggested code', $mapped],
            ['Flagged rows', $flagged],
            ['Date range', $dates ? reset($dates).' to '.end($dates) : '-'],
        ]);

        $this->line('Fill the Employee Code column on the "Employee Map" sheet, then import the file.');
    }
}
