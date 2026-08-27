<?php

namespace App\Services\Attendance;

use App\Services\Excel\FormatsWorkbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
        return $statement['mode'].'-attendance-'
            .$this->slugForFilename((string) $statement['subject']['name'], 'statement').'-'
            .$statement['filters']['from'].'-to-'.$statement['filters']['to'].'.xlsx';
    }
}
