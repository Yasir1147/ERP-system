<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class EmployeeProjectSheetExporter
{
    use FormatsWorkbook;

    public function download(array $report)
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Employee Project Salary');
        $lastIndex = 9 + count($report['projects']) * 4;
        $last = Coordinate::stringFromColumnIndex($lastIndex);
        $sheet->mergeCells('A1:'.$last.'1')->setCellValue('A1', 'EMPLOYEE PROJECT SALARY SHEET');
        $sheet->mergeCells('A2:'.$last.'2')->setCellValue('A2', $report['filters']['from'].' to '.$report['filters']['to'].' | Currency: AED | Current payroll rates');
        $sheet->mergeCells('A3:'.$last.'3')->setCellValue('A3', 'Days = full/half labour days; OT Hours = decimal hours. Bonus = saved monthly bonuses for months in range, not allocated to projects. Missing payroll: '.$report['missingPayroll']);
        $headings = ['S.No', 'Name / Code', 'Salary (AED)', 'OT Cost (AED)', 'Per Day (AED)', 'Bonus (AED)'];
        foreach ($headings as $i => $heading) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->mergeCells($col.'5:'.$col.'6')->setCellValue($col.'5', $heading);
        }
        $column = 7;
        foreach ($report['projects'] as $project) {
            $start = Coordinate::stringFromColumnIndex($column);
            $end = Coordinate::stringFromColumnIndex($column + 3);
            $sheet->mergeCells($start.'5:'.$end.'5')->setCellValueExplicit($start.'5', trim(($project['code'] ? $project['code'].' - ' : '').$project['name']), DataType::TYPE_STRING);
            foreach (['Days', 'OT Hours', 'Salary Cost (AED)', 'OT Cost (AED)'] as $heading) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column++).'6', $heading);
            }
        }
        foreach (['Total Days', 'Total OT Hours', 'Total (AED)'] as $heading) {
            $col = Coordinate::stringFromColumnIndex($column++);
            $sheet->mergeCells($col.'5:'.$col.'6')->setCellValue($col.'5', $heading);
        }
        $row = 7;
        foreach ($report['rows'] as $index => $item) {
            $values = [$index + 1, trim($item['name'].' '.$item['code']).($item['missingPayroll'] ? ' (Missing payroll)' : ''), $item['basicCost'], $item['overtimeCost'], $item['dailySalary'] ?? 'Missing', $item['bonus']];
            foreach ($item['projects'] as $cell) {
                array_push($values, $cell['labourDays'], $cell['overtimeHours'], $cell['basicCost'], $cell['overtimeCost']);
            }
            array_push($values, $item['labourDays'], $item['overtimeHours'], $item['totalCost']);
            foreach ($values as $i => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).$row, $value, is_numeric($value) && $i !== 1 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            if ($index % 2 === 1) {
                $sheet->getStyle('A'.$row.':'.$last.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE7E6E6');
            }
            $row++;
        }
        $sheet->setCellValue('B'.$row, 'Totals');
        for ($i = 3; $i <= $lastIndex; $i++) {
            if ($i === 5) {
                continue; // A rate must not be summed.
            }
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->setCellValue($col.$row, $row > 7 ? '=SUM('.$col.'7:'.$col.($row - 1).')' : 0);
        }
        $sheet->getStyle('C7:'.$last.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A1:'.$last.$row)->getFont()->setName('Arial')->setSize(10);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A5:'.$last.'6')->getFont()->setBold(true);
        $sheet->getStyle('A5:'.$last.'6')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFD9D9D9');
        $sheet->getStyle('A5:'.$last.'6')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $this->drawGrid($sheet, 'A5:'.$last.$row);
        $this->styleTotalRow($sheet, 'A'.$row.':'.$last.$row);
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(40);
        for ($i = 3; $i <= $lastIndex; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }
        $sheet->getRowDimension(3)->setRowHeight(30);
        $sheet->getStyle('A3')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(5)->setRowHeight(30);
        $sheet->getRowDimension(6)->setRowHeight(30);
        $sheet->freezePane('C7');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setRowsToRepeatAtTopByStartAndEnd(5, 6)->setFitToWidth(0)->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea('A1:'.$last.$row);

        return $this->streamWorkbook($book, 'employee-project-salary-'.$report['filters']['from'].'-to-'.$report['filters']['to'].'.xlsx');
    }
}
