<?php

namespace App\Services\Attendance;

use App\Services\Excel\FormatsWorkbook;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the day-by-day attendance workbook.
 *
 * One row per day, in date order, because the file is opened by someone
 * checking a particular day rather than reading a summary.
 */
class AttendanceStatementExporter
{
    use FormatsWorkbook;

    /**
     * @param  array<string, mixed>  $statement
     */
    public function download(array $statement): StreamedResponse
    {
        return $this->streamWorkbook($this->build($statement), $this->filename($statement));
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function build(array $statement): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        if (($statement['layout'] ?? 'list') === 'grid') {
            $this->buildGrid($sheet, $statement);

            $spreadsheet->getProperties()
                ->setTitle($this->title($statement))
                ->setSubject($statement['subject']['name'])
                ->setCompany('Al Mohafiz Building Contracting LLC');

            return $spreadsheet;
        }

        $headings = $this->headings($statement);
        $lastColumn = chr(ord('A') + count($headings) - 1);

        $row = $this->writeCompanyHeader($sheet, $this->title($statement), $lastColumn, $this->meta($statement));
        $row = $this->writeStatStrip($sheet, $this->stats($statement), $row);
        $this->writeRows($sheet, $statement, $headings, $lastColumn, $row);

        $spreadsheet->getProperties()
            ->setTitle($this->title($statement))
            ->setSubject($statement['subject']['name'])
            ->setCompany('Al Mohafiz Building Contracting LLC');

        return $spreadsheet;
    }

    /**
     * People down the side, worked days across the top, one letter per cell.
     *
     * This is the shape the site reads: a supervisor scanning a column asks
     * "who was on site that day", and scanning a row asks "how many days did
     * this man work". A list of rows answers neither at a glance.
     *
     * @param  array<string, mixed>  $statement
     */
    private function buildGrid(Worksheet $sheet, array $statement): void
    {
        $matrix = $statement['matrix'];
        $dates = $matrix['dates'];
        $people = $matrix['people'];

        // Name column, then one per date, then Present / Absent / Not listed.
        $lastColumn = $this->columnLetter(1 + count($dates) + 3);

        $sheet->setCellValue('A1', 'Al Mohafiz Building Contracting LLC');
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', $statement['subject']['name'].' - attendance by person');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF9B2C2C');

        $sheet->setCellValue('A3', $statement['rangeLabel'].'   ·   Generated '.now()->format('d/m/Y h:i A'));
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setARGB('FF46545F');

        $headerRow = 5;
        $sheet->setCellValue('A'.$headerRow, 'Name');

        foreach ($dates as $index => $date) {
            $sheet->setCellValue($this->columnLetter(2 + $index).$headerRow, $date['label']);
        }

        $summaryStart = 2 + count($dates);
        $sheet->setCellValue($this->columnLetter($summaryStart).$headerRow, 'Days Present');
        $sheet->setCellValue($this->columnLetter($summaryStart + 1).$headerRow, 'Days Absent');
        $sheet->setCellValue($this->columnLetter($summaryStart + 2).$headerRow, 'Not listed');

        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC0504D']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'textRotation' => 90,
            ],
        ]);
        $sheet->getStyle('A'.$headerRow)->getAlignment()->setTextRotation(0)->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($headerRow)->setRowHeight(58);

        $row = $headerRow + 1;
        $firstDataRow = $row;

        foreach ($people as $person) {
            $sheet->setCellValue('A'.$row, trim(($person['employeeCode'] ? $person['employeeCode'].' - ' : '').$person['employeeName']));

            foreach ($person['cells'] as $index => $cell) {
                $column = $this->columnLetter(2 + $index);
                $sheet->setCellValue($column.$row, $cell['code'] === '-' ? '–' : $cell['code']);
                $sheet->getStyle($column.$row)->applyFromArray([
                    'font' => ['bold' => $cell['code'] !== '-', 'size' => 9, 'color' => ['argb' => $this->cellFontColour($cell['code'])]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->cellFillColour($cell['code'])]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            $sheet->setCellValue($this->columnLetter($summaryStart).$row, $person['presentDays']);
            $sheet->setCellValue($this->columnLetter($summaryStart + 1).$row, $person['absentDays']);
            $sheet->setCellValue($this->columnLetter($summaryStart + 2).$row, $person['notListed']);

            $row++;
        }

        $lastDataRow = $row - 1;

        // Per-day counts, so a column can be read on its own.
        $sheet->setCellValue('A'.$row, 'Headcount present that day');
        $sheet->setCellValue('A'.($row + 1), 'Marked absent that day');

        foreach ($matrix['footer'] as $index => $day) {
            $column = $this->columnLetter(2 + $index);
            $sheet->setCellValue($column.$row, $day['present']);
            $sheet->setCellValue($column.($row + 1), $day['absent']);
        }

        $sheet->setCellValue($this->columnLetter($summaryStart).$row, $matrix['footerTotals']['present']);
        $sheet->setCellValue($this->columnLetter($summaryStart).($row + 1), $matrix['footerTotals']['absent']);

        $sheet->getStyle('A'.$row.':'.$lastColumn.($row + 1))->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF9B2C2C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle('A'.$row.':A'.($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A'.($row + 3), 'P = Present    H = Half day    A = Absent    L = Leave    – = Not listed that day');
        $sheet->getStyle('A'.($row + 3))->getFont()->setSize(9)->getColor()->setARGB('FF46545F');

        if ($lastDataRow >= $firstDataRow) {
            $this->drawGrid($sheet, 'A'.$headerRow.':'.$lastColumn.($row + 1));
        }

        $sheet->getColumnDimension('A')->setWidth(28);

        foreach ($dates as $index => $ignored) {
            $sheet->getColumnDimension($this->columnLetter(2 + $index))->setWidth(4.5);
        }

        foreach (range(0, 2) as $offset) {
            $sheet->getColumnDimension($this->columnLetter($summaryStart + $offset))->setWidth(12);
        }

        $sheet->freezePane('B'.($headerRow + 1));
    }

    private function cellFillColour(string $code): string
    {
        return match ($code) {
            'P' => 'FFDCFCE7',
            'H' => 'FFFEF3C7',
            'A' => 'FFFEE2E2',
            'L' => 'FFFEF3C7',
            default => 'FFF3F4F6',
        };
    }

    private function cellFontColour(string $code): string
    {
        return match ($code) {
            'P' => 'FF166534',
            'H', 'L' => 'FF92400E',
            'A' => 'FF991B1B',
            default => 'FF9CA3AF',
        };
    }

    /** Past column Z the letters double, so they cannot be built with chr(). */
    private function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return list<string>
     */
    private function headings(array $statement): array
    {
        $headings = ['Date', 'Day'];

        if ($statement['mode'] === 'project') {
            $headings[] = 'Code';
            $headings[] = 'Employee';
            $headings[] = 'Profession';
        }

        $headings[] = 'Project';
        $headings[] = 'Status';
        $headings[] = 'Day Value';
        $headings[] = 'OT Hours';
        $headings[] = 'Note';

        if ($statement['withSalary']) {
            $headings[] = 'Daily Salary';
            $headings[] = 'Basic Cost';
            $headings[] = 'OT Cost';
            $headings[] = 'Total Cost';
        }

        return $headings;
    }

    /**
     * @param  array<string, mixed>  $statement
     * @param  list<string>  $headings
     */
    private function writeRows(Worksheet $sheet, array $statement, array $headings, string $lastColumn, int $row): void
    {
        $this->writeTableHeadings($sheet, $headings, $row);

        $headerRow = $row;
        $row++;
        $firstDataRow = $row;

        foreach ($statement['rows'] as $entry) {
            $values = [$entry['date'], $entry['weekday']];

            if ($statement['mode'] === 'project') {
                $values[] = $entry['employeeCode'];
                $values[] = $entry['employeeName'];
                $values[] = $entry['profession'];
            }

            $values[] = $entry['projectName'] ?: '-';
            $values[] = ucfirst((string) $entry['status']);
            $values[] = $entry['dayValue'];
            $values[] = $entry['overtimeHours'];
            $values[] = $entry['note'] ?: '';

            if ($statement['withSalary']) {
                $values[] = $entry['dailySalary'];
                $values[] = $entry['basicCost'];
                $values[] = $entry['overtimeCost'];
                $values[] = $entry['totalCost'];
            }

            $column = 'A';

            foreach ($values as $value) {
                $sheet->setCellValue($column.$row, $value);
                $column++;
            }

            $row++;
        }

        $lastDataRow = $row - 1;

        if ($lastDataRow >= $firstDataRow) {
            if ($statement['withSalary']) {
                $moneyFrom = chr(ord($lastColumn) - 3);
                $sheet->getStyle($moneyFrom.$firstDataRow.':'.$lastColumn.$lastDataRow)
                    ->getNumberFormat()
                    ->setFormatCode(self::MONEY_FORMAT);
            }

            $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$lastDataRow);
        }

        // Totals row, so the sheet foots to the same figures as the strip.
        $totals = $statement['totals'];
        $sheet->setCellValue('A'.$row, 'TOTAL');
        $dayValueColumn = $statement['mode'] === 'project' ? 'H' : 'E';
        $overtimeColumn = $statement['mode'] === 'project' ? 'I' : 'F';
        $sheet->setCellValue($dayValueColumn.$row, $totals['presentDays']);
        $sheet->setCellValue($overtimeColumn.$row, $totals['overtimeHours']);

        if ($statement['withSalary']) {
            $sheet->setCellValue(chr(ord($lastColumn) - 2).$row, $totals['basicCost']);
            $sheet->setCellValue(chr(ord($lastColumn) - 1).$row, $totals['overtimeCost']);
            $sheet->setCellValue($lastColumn.$row, $totals['totalCost']);
            $sheet->getStyle(chr(ord($lastColumn) - 2).$row.':'.$lastColumn.$row)
                ->getNumberFormat()
                ->setFormatCode(self::MONEY_FORMAT);
        }

        $this->styleTotalRow($sheet, 'A'.$row.':'.$lastColumn.$row);
        $this->drawGrid($sheet, 'A'.$headerRow.':'.$lastColumn.$row);
        $sheet->freezePane('A'.($headerRow + 1));

        $this->applyColumnWidths($sheet, $this->widths($statement));
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, int>
     */
    private function widths(array $statement): array
    {
        $widths = ['A' => 13, 'B' => 7];
        $column = 'C';

        if ($statement['mode'] === 'project') {
            $widths['C'] = 10;
            $widths['D'] = 26;
            $widths['E'] = 20;
            $column = 'F';
        }

        foreach ([24, 12, 11, 10, 26] as $width) {
            $widths[$column] = $width;
            $column++;
        }

        if ($statement['withSalary']) {
            foreach ([13, 13, 12, 13] as $width) {
                $widths[$column] = $width;
                $column++;
            }
        }

        return $widths;
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, string>
     */
    private function meta(array $statement): array
    {
        $subject = $statement['subject'];
        $label = $statement['mode'] === 'project' ? 'Project' : 'Employee';

        return [
            $label => trim(($subject['code'] ? $subject['code'].' — ' : '').$subject['name']),
            'Category' => (string) $subject['typeLabel'],
            'Status' => ucfirst((string) $subject['status']),
            'Date Range' => (string) $statement['rangeLabel'],
            'Generated' => now()->format('d/m/Y h:i A'),
        ];
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<int, array{0: string, 1: mixed, 2: bool}>
     */
    private function stats(array $statement): array
    {
        $totals = $statement['totals'];

        $pairs = [
            ['Present Days', $totals['presentDays'], false],
            ['Absent', $totals['absent'], false],
            ['Leave', $totals['leave'], false],
            ['Overtime Hours', $totals['overtimeHours'], false],
        ];

        if ($statement['mode'] === 'project') {
            $pairs[] = ['Employees', $totals['uniqueEmployees'], false];
        } else {
            $pairs[] = ['Projects', $totals['projects'], false];
        }

        if ($statement['withSalary']) {
            $pairs[] = ['Basic Cost', $totals['basicCost'], true];
            $pairs[] = ['Overtime Cost', $totals['overtimeCost'], true];
            $pairs[] = ['Total Cost', $totals['totalCost'], true];
        }

        return $pairs;
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function title(array $statement): string
    {
        return $statement['mode'] === 'project' ? 'Project Attendance Statement' : 'Employee Attendance Statement';
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function filename(array $statement): string
    {
        return $statement['mode'].'-attendance-'.($statement['layout'] ?? 'list').'-'
            .$this->slugForFilename((string) $statement['subject']['name'], 'statement').'-'
            .$statement['filters']['from'].'-to-'.$statement['filters']['to'].'.xlsx';
    }
}
