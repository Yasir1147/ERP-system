<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProjectAttendanceSummaryExporter
{
    use FormatsWorkbook;

    public function downloadHistory(array $history)
    {
        $book = new Spreadsheet;
        foreach (['Employee Summary', 'Daily Attendance'] as $index => $title) {
            $sheet = $index === 0 ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($title);
            $last = $index === 0 ? 'L' : 'K';
            $row = $this->writeCompanyHeader($sheet, 'Project Employee History - '.$history['project']['name'], $last, []);
            $sheet->mergeCells('A'.$row.':'.$last.$row);
            $sheet->setCellValue('A'.$row++, $history['filters']['from'].' to '.$history['filters']['to'].' | Internal | AED | Salary + overtime only');
            $total = $history['totals'];
            $sheet->mergeCells('A'.$row.':'.$last.$row);
            $sheet->setCellValue('A'.$row++, 'Salary Cost: '.number_format($total['basicCost'], 2).' | OT Cost: '.number_format($total['overtimeCost'], 2).' | Total: '.number_format($total['totalCost'], 2).' | Missing payroll: '.$total['missingPayroll']);
            $headings = $index === 0
                ? ['Code', 'Employee', 'Profession', 'Entries', 'Worked Days', 'Labour Days', 'OT (h:mm)', 'Salary Cost', 'OT Cost', 'Total Cost', 'Share %', 'Submitted By']
                : ['Date', 'Employee', 'Main Project', 'OT Project', 'Labour Days', 'Daily Salary', 'OT (h:mm)', 'Salary Cost', 'OT Cost', 'Total Cost', 'Submitted By'];
            $header = ++$row;
            $this->writeTableHeadings($sheet, $headings, $row++);
            foreach ($history[$index === 0 ? 'employees' : 'records'] as $item) {
                $values = $index === 0
                    ? [$item['employeeCode'] ?? '-', $item['employeeName'].($item['missingPayroll'] ? ' (Missing payroll)' : ''), $item['profession'] ?? '-', $item['entries'], $item['workedDays'], $item['labourDays'], $item['overtimeHours'] / 24, $item['basicCost'], $item['overtimeCost'], $item['totalCost'], $item['costShare'], $item['submittedBy']]
                    : [$item['date'], trim(($item['employeeCode'] ?? '').' - '.$item['employeeName']), $item['mainProject'] ?? '-', $item['overtimeProject'] ?? '-', $item['labourDays'], $item['dailySalary'], $item['overtimeHours'] / 24, round($item['basicCost'], 2), round($item['overtimeCost'], 2), round($item['totalCost'], 2), $item['submittedBy']];
                foreach ($values as $column => $value) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).$row, $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $sheet->getRowDimension($row++)->setRowHeight(32);
            }
            $sheet->setCellValue('A'.$row, 'Project Total');
            foreach (['H' => 'basicCost', 'I' => 'overtimeCost', 'J' => 'totalCost'] as $column => $key) {
                $sheet->setCellValue($column.$row, $total[$key]);
            }
            $sheet->setCellValue('G'.$row, $total['overtimeHours'] / 24);
            $sheet->getStyle('G'.($header + 1).':G'.$row)->getNumberFormat()->setFormatCode('[h]:mm');
            $sheet->getStyle('H'.($header + 1).':J'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $this->styleTotalRow($sheet, 'A'.$row.':'.$last.$row);
            $this->drawGrid($sheet, 'A'.$header.':'.$last.$row);
            foreach (range('A', $last) as $column) {
                $sheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'C', 'D', $last]) ? 28 : 16);
            }
            $sheet->getStyle('A1:'.$last.$row)->getAlignment()->setWrapText(true);
            $sheet->setAutoFilter('A'.$header.':'.$last.max($header, $row - 1));
            $sheet->freezePane('C'.($header + 1));
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($header, $header);
        }
        $book->setActiveSheetIndex(0);

        return $this->streamWorkbook($book, 'project-'.$history['project']['id'].'-employee-history-'.$history['filters']['from'].'-to-'.$history['filters']['to'].'.xlsx');
    }

    public function download(array $report)
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Project Summary');
        $row = $this->writeCompanyHeader($sheet, 'Project Attendance & Labour Cost Summary', 'I', []);
        $sheet->mergeCells('A'.$row.':I'.$row);
        $sheet->setCellValue('A'.$row++, $report['filters']['from'].' to '.$report['filters']['to'].' | Internal salary information | Currency: AED');
        $header = ++$row;
        $this->writeTableHeadings($sheet, ['Project', 'Worked Days', 'Persons', 'Labour Days', 'Overtime (h:mm)', 'Salary Cost (AED)', 'OT Cost (AED)', 'Total Cost (AED)', 'Missing Payroll'], $row++);
        foreach ($report['rows'] as $item) {
            $values = [trim(($item['code'] ? $item['code'].' - ' : '').$item['name']), $item['workedDays'], $item['persons'], $item['labourDays'], $item['overtimeHours'] / 24, $item['basicCost'], $item['overtimeCost'], $item['totalCost'], $item['missingPayroll']];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, $index === 0 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
            $row++;
        }
        $total = $report['totals'];
        $values = ['Totals (persons unique)', $total['workedDays'], $total['persons'], $total['labourDays'], $total['overtimeHours'] / 24, $total['basicCost'], $total['overtimeCost'], $total['totalCost'], $total['missingPayroll']];
        foreach ($values as $index => $value) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, $index === 0 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
        }
        $sheet->getStyle('E'.($header + 1).':E'.$row)->getNumberFormat()->setFormatCode('[h]:mm');
        $sheet->getStyle('F'.($header + 1).':H'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        $this->styleTotalRow($sheet, 'A'.$row.':I'.$row);
        $this->drawGrid($sheet, 'A'.$header.':I'.$row);
        $sheet->mergeCells('A'.($row + 2).':I'.($row + 2));
        $sheet->setCellValue('A'.($row + 2), 'Worked Days = distinct dates per project. Labour Days = full/half days added per worker. Persons in totals are unique across projects.');
        $sheet->mergeCells('A'.($row + 3).':I'.($row + 3));
        $sheet->setCellValue('A'.($row + 3), 'Current payroll rates; salary + overtime only. Excludes overhead, purchases and expenses. Missing payroll makes cost incomplete.');
        $sheet->getColumnDimension('A')->setWidth(48);
        foreach (range('B', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->getStyle('A1:I'.($row + 3))->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($header)->setRowHeight(32);
        $sheet->getRowDimension($row + 2)->setRowHeight(28);
        $sheet->getRowDimension($row + 3)->setRowHeight(28);
        $sheet->setAutoFilter('A'.$header.':I'.max($header, $row - 1));
        $sheet->freezePane('B'.($header + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($header, $header);

        return $this->streamWorkbook($book, 'project-summary-'.$report['filters']['from'].'-to-'.$report['filters']['to'].'.xlsx');
    }
}
