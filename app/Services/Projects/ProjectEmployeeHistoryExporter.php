<?php

namespace App\Services\Projects;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the project labour workbook.
 *
 * The sheet is meant to be read by someone asking "what did this project's
 * labour cost, and who drove it", so rows are ordered by cost and carry a
 * share-of-total column rather than being a raw dump.
 */
class ProjectEmployeeHistoryExporter
{
    private const HEADER_FILL = 'FF1B4B73';
    private const TOTAL_FILL = 'FFEDF0F3';
    private const WARNING_FILL = 'FFFCF1D8';

    private const MONEY_FORMAT = '#,##0.00';

    /**
     * @param  array<string, mixed>  $history
     */
    public function download(array $history): StreamedResponse
    {
        $spreadsheet = $this->build($history);
        $filename = $this->filename($history);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $history
     */
    private function build(array $history): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employee Summary');

        $row = $this->writeHeader($sheet, $history);
        $row = $this->writeTotals($sheet, $history, $row);
        $row = $this->writeWarning($sheet, $history, $row);
        $this->writeTable($sheet, $history, $row);

        $this->applyLayout($sheet);

        $spreadsheet->getProperties()
            ->setTitle('Project Employee History')
            ->setSubject($history['project']['name'])
            ->setCompany('Al Mohafiz Building Contracting LLC');

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $history
     */
    private function writeHeader(Worksheet $sheet, array $history): int
    {
        $project = $history['project'];

        $sheet->setCellValue('A1', 'Al Mohafiz Building Contracting LLC');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Project Employee History');
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setSize(11)->getColor()->setARGB('FF46545F');

        $sheet->setCellValue('A4', 'Project');
        $sheet->setCellValue('B4', trim(($project['code'] ? $project['code'].' — ' : '').$project['name']));
        $sheet->setCellValue('A5', 'Category');
        $sheet->setCellValue('B5', $project['typeLabel']);
        $sheet->setCellValue('A6', 'Status');
        $sheet->setCellValue('B6', ucfirst((string) $project['status']));
        $sheet->setCellValue('A7', 'Date Range');
        $sheet->setCellValue('B7', $history['rangeLabel']);
        $sheet->setCellValue('A8', 'Generated');
        $sheet->setCellValue('B8', now()->format('d/m/Y h:i A'));

        $sheet->getStyle('A4:A8')->getFont()->setBold(true);

        return 10;
    }

    /**
     * @param  array<string, mixed>  $history
     */
    private function writeTotals(Worksheet $sheet, array $history, int $row): int
    {
        $totals = $history['totals'];

        $pairs = [
            ['Number of Person', $totals['uniqueEmployees'], false],
            ['Head Count', $totals['entries'], false],
            ['Worked Days', $totals['workedDays'], false],
            ['Overtime Hours', $totals['overtimeHours'], false],
            ['Basic Cost', $totals['basicCost'], true],
            ['Overtime Cost', $totals['overtimeCost'], true],
            ...(($history['overhead']['enabled'] ?? false)
                ? [['Overhead', $totals['overheadCost'], true]]
                : []),
            ['Total Labour Cost', $totals['totalCost'], true],
        ];

        $column = 'A';

        foreach ($pairs as [$label, $value, $isMoney]) {
            $sheet->setCellValue($column.$row, $label);
            $sheet->setCellValue($column.($row + 1), $value);

            $sheet->getStyle($column.$row)->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle($column.$row)->getFont()->getColor()->setARGB('FF46545F');
            $sheet->getStyle($column.($row + 1))->getFont()->setBold(true)->setSize(12);

            if ($isMoney) {
                $sheet->getStyle($column.($row + 1))
                    ->getNumberFormat()
                    ->setFormatCode(self::MONEY_FORMAT);
            }

            $column++;
        }

        $sheet->getStyle('A'.$row.':G'.($row + 1))
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::TOTAL_FILL);

        return $row + 3;
    }

    /**
     * A total that silently excludes uncosted employees is misleading, so the
     * workbook says so above the table rather than in a footnote.
     *
     * @param  array<string, mixed>  $history
     */
    private function writeWarning(Worksheet $sheet, array $history, int $row): int
    {
        $missing = $history['missingPayrollEmployees'];

        if (count($missing) === 0) {
            return $row;
        }

        $sheet->setCellValue(
            'A'.$row,
            'Cost is incomplete. No salary setting for: '.implode(', ', $missing->all()),
        );
        $sheet->mergeCells('A'.$row.':J'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->getColor()->setARGB('FF8A6100');
        $sheet->getStyle('A'.$row)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::WARNING_FILL);

        return $row + 2;
    }

    /**
     * @param  array<string, mixed>  $history
     */
    private function writeTable(Worksheet $sheet, array $history, int $row): void
    {
        // Overhead earns a column only when it is switched on, so a workbook
        // produced with it off reads exactly as it always did.
        $hasOverhead = (bool) ($history['overhead']['enabled'] ?? false);
        $overheadColumn = $hasOverhead ? 'I' : null;
        $totalColumn = $hasOverhead ? 'J' : 'I';
        $shareColumn = $hasOverhead ? 'K' : 'J';

        $headings = array_values(array_filter([
            'Code',
            'Employee',
            'Profession',
            'Entries',
            'Worked Days',
            'OT Hours',
            'Basic Cost',
            'OT Cost',
            $hasOverhead ? 'Overhead' : null,
            'Total Cost',
            'Share %',
        ]));

        $headerRow = $row;
        $column = 'A';

        foreach ($headings as $heading) {
            $sheet->setCellValue($column.$headerRow, $heading);
            $column++;
        }

        $sheet->getStyle('A'.$headerRow.':'.$shareColumn.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::HEADER_FILL],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        $row = $headerRow + 1;
        $firstDataRow = $row;

        foreach ($history['employeeSummary'] as $employee) {
            $sheet->setCellValue('A'.$row, $employee['employeeCode']);
            $sheet->setCellValue('B'.$row, $employee['employeeName']);
            $sheet->setCellValue('C'.$row, $employee['profession']);
            $sheet->setCellValue('D'.$row, $employee['entries']);
            $sheet->setCellValue('E'.$row, $employee['workedDays']);
            $sheet->setCellValue('F'.$row, $employee['overtimeHours']);
            $sheet->setCellValue('G'.$row, $employee['basicCost']);
            $sheet->setCellValue('H'.$row, $employee['overtimeCost']);

            if ($overheadColumn) {
                $sheet->setCellValue($overheadColumn.$row, $employee['overheadCost']);
            }

            $sheet->setCellValue($totalColumn.$row, $employee['totalCost']);
            $sheet->setCellValue($shareColumn.$row, $employee['costShare'] / 100);

            if ($employee['missingPayrollSetting']) {
                $sheet->getStyle('A'.$row.':'.$shareColumn.$row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::WARNING_FILL);
            }

            $row++;
        }

        $lastDataRow = $row - 1;

        if ($lastDataRow >= $firstDataRow) {
            $sheet->getStyle('G'.$firstDataRow.':'.$totalColumn.$lastDataRow)
                ->getNumberFormat()
                ->setFormatCode(self::MONEY_FORMAT);
            $sheet->getStyle($shareColumn.$firstDataRow.':'.$shareColumn.$lastDataRow)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

            $sheet->setAutoFilter('A'.$headerRow.':'.$shareColumn.$lastDataRow);
        }

        // Totals row, so the table foots to the same number as the summary.
        $sheet->setCellValue('A'.$row, 'TOTAL');
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('D'.$row, $history['totals']['entries']);
        $sheet->setCellValue('E'.$row, $history['totals']['workedDays']);
        $sheet->setCellValue('F'.$row, $history['totals']['overtimeHours']);
        $sheet->setCellValue('G'.$row, $history['totals']['basicCost']);
        $sheet->setCellValue('H'.$row, $history['totals']['overtimeCost']);

        if ($overheadColumn) {
            $sheet->setCellValue($overheadColumn.$row, $history['totals']['overheadCost']);
        }

        $sheet->setCellValue($totalColumn.$row, $history['totals']['totalCost']);
        $sheet->setCellValue($shareColumn.$row, $history['totals']['totalCost'] > 0 ? 1 : 0);

        $sheet->getStyle('A'.$row.':'.$shareColumn.$row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::TOTAL_FILL],
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
        $sheet->getStyle('G'.$row.':'.$totalColumn.$row)
            ->getNumberFormat()
            ->setFormatCode(self::MONEY_FORMAT);
        $sheet->getStyle($shareColumn.$row)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        $sheet->getStyle('A'.$headerRow.':'.$shareColumn.$row)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)
            ->getColor()
            ->setARGB('FFD5DCE3');

        $sheet->freezePane('A'.($headerRow + 1));
    }

    private function applyLayout(Worksheet $sheet): void
    {
        $widths = [
            'A' => 10,
            'B' => 30,
            'C' => 22,
            'D' => 10,
            'E' => 13,
            'F' => 11,
            'G' => 14,
            'H' => 13,
            'I' => 15,
            'J' => 15,
            'K' => 10,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /**
     * @param  array<string, mixed>  $history
     */
    private function filename(array $history): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $history['project']['name']);
        $name = trim((string) $name, '-') ?: 'project';

        return 'employee-history-'.strtolower($name).'-'.now()->format('Y-m-d').'.xlsx';
    }
}
