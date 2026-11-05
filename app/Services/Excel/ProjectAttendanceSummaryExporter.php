<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProjectAttendanceSummaryExporter
{
    use FormatsWorkbook;

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
