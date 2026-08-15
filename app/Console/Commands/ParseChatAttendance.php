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
        {--type=contracting : Employee type to match names against}
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

        if ($rows === []) {
            $this->warn('No attendance rows matched.');

            return self::SUCCESS;
        }

        $type = (string) $this->option('type');
        $matcher = new ChatEmployeeMatcher(
            Employee::query()->where('type', $type)->get(),
        );

        $rows = array_map(function (array $row) use ($matcher) {
            $result = $matcher->match($row['sourceName']);

            return $row + [
                'matchStatus' => $result['status'],
                'employeeCode' => $result['employee']['code'] ?? null,
                'employeeName' => $result['employee']['name'] ?? null,
                'candidates' => implode(' | ', $result['candidates']),
            ];
        }, $rows);

        $out = $this->option('out') ?: storage_path('app/chat-attendance-'.now()->format('Ymd-His').'.xlsx');

        $this->write($rows, $out);
        $this->report($rows, $out);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function write(array $rows, string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Import');

        $project = (string) ($this->option('project') ?? '');

        $headerRow = 1;

        $this->writeTableHeadings($sheet, [
            'Date',
            'Employee Code',
            'Employee Name',
            'Project',
            'Status',
            'Day',
            'Overtime Hours',
            'Overtime Project',
            'Note',
            'Chat Name',
            'Match',
            'Candidates',
            'Chat Heading',
            'Flags',
        ], $headerRow);

        $row = $headerRow + 1;

        foreach ($rows as $entry) {
            $sheet->setCellValue('A'.$row, $entry['date']);
            $sheet->setCellValue('B'.$row, $entry['employeeCode']);
            $sheet->setCellValue('C'.$row, $entry['employeeName']);
            $sheet->setCellValue('D'.$row, $project !== '' ? $project : $entry['project']);
            $sheet->setCellValue('E'.$row, ucfirst($entry['status']));
            $sheet->setCellValue('F'.$row, $entry['attendanceFraction'] == 0.5 ? 'Half' : 'Full');
            $sheet->setCellValue('G'.$row, in_array('overtime', $entry['flags'], true) ? '' : '');
            $sheet->setCellValue('H'.$row, '');
            $sheet->setCellValue('I'.$row, $entry['note']);
            $sheet->setCellValue('J'.$row, $entry['sourceName']);
            $sheet->setCellValue('K'.$row, $entry['matchStatus']);
            $sheet->setCellValue('L'.$row, $entry['candidates']);
            $sheet->setCellValue('M'.$row, $entry['project']);
            $sheet->setCellValue('N'.$row, implode(', ', $entry['flags']));

            $sheet->getStyle('B'.$row)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);

            // Rows a person must look at are coloured, so a long sheet can be
            // scanned rather than read line by line.
            $needsAttention = $entry['matchStatus'] !== ChatEmployeeMatcher::MATCHED
                || $entry['flags'] !== [];

            if ($needsAttention) {
                $sheet->getStyle('A'.$row.':N'.$row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::WARNING_FILL);
            }

            $row++;
        }

        $lastRow = $row - 1;

        $sheet->setAutoFilter('A'.$headerRow.':N'.$lastRow);
        $this->drawGrid($sheet, 'A'.$headerRow.':N'.$lastRow);
        $sheet->freezePane('A'.($headerRow + 1));

        $this->applyColumnWidths($sheet, [
            'A' => 12, 'B' => 14, 'C' => 26, 'D' => 22, 'E' => 10, 'F' => 8,
            'G' => 14, 'H' => 18, 'I' => 22, 'J' => 22, 'K' => 12, 'L' => 34,
            'M' => 24, 'N' => 14,
        ]);

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function report(array $rows, string $path): void
    {
        $matched = count(array_filter($rows, fn ($r) => $r['matchStatus'] === ChatEmployeeMatcher::MATCHED));
        $ambiguous = count(array_filter($rows, fn ($r) => $r['matchStatus'] === ChatEmployeeMatcher::AMBIGUOUS));
        $missing = count(array_filter($rows, fn ($r) => $r['matchStatus'] === ChatEmployeeMatcher::NOT_FOUND));
        $flagged = count(array_filter($rows, fn ($r) => $r['flags'] !== []));

        $dates = array_filter(array_column($rows, 'date'));
        sort($dates);

        $this->newLine();
        $this->info('Written: '.$path);
        $this->newLine();

        $this->table(['', 'Count'], [
            ['Rows', count($rows)],
            ['Matched to an employee', $matched],
            ['Ambiguous — you choose', $ambiguous],
            ['No employee found', $missing],
            ['Flagged (supply, unknown marker)', $flagged],
            ['Dates covered', $dates ? reset($dates).' to '.end($dates) : '-'],
        ]);

        if ($ambiguous || $missing) {
            $this->warn('Fill the Employee Code column for the highlighted rows before importing.');
        }
    }
}
