<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class OfficeTimesheetExporter
{
    use FormatsWorkbook;

    public function download(array $data)
    {
        return $this->streamWorkbook($this->build($data), 'office-timesheet-'.$data['filters']['month'].'.xlsx');
    }

    public function build(array $data): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Monthly Timesheet');
        $last = Coordinate::stringFromColumnIndex(count($data['days']) + 5);
        $row = $this->writeCompanyHeader($sheet, 'Office Staff Monthly Timesheet — '.$data['monthLabel'], $last, []);
        $sheet->mergeCells('A'.$row.':'.$last.$row);
        $sheet->setCellValue('A'.$row++, 'P: Present | L: Approved leave | LP: Pending leave | -: No record (not absent). Weekly off-days are not assumed.');
        $sheet->mergeCells('A'.$row.':'.$last.$row);
        $sheet->setCellValue('A'.$row++, 'Work hours follow Attendance Report rules. Open sessions today show elapsed hours.');
        $header = ++$row;
        $this->writeTableHeadings($sheet, ['Staff', ...array_map(fn ($day) => $day['number'].' '.$day['weekday'], $data['days']), 'Present', 'Leave', 'Pending', 'Work Hours'], $row++);
        $sheet->getRowDimension($header)->setRowHeight(60);
        $dateEnd = Coordinate::stringFromColumnIndex(count($data['days']) + 1);
        $sheet->getStyle('B'.$header.':'.$dateEnd.$header)->getAlignment()->setTextRotation(90);
        foreach ($data['rows'] as $person) {
            $values = [$person['code'].' - '.$person['name'], ...array_column($person['cells'], 'status'), $person['present'], $person['leave'], $person['pending'], $person['workMinutes'] / 1440];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, is_numeric($value) && $index > 0 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            foreach ($person['cells'] as $index => $cell) {
                $color = match ($cell['status']) {
                    'P' => 'DCFCE7', 'L' => 'FEF3C7', 'LP' => 'DBEAFE', default => 'F8FAFC'
                };
                $sheet->getStyle(Coordinate::stringFromColumnIndex($index + 2).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
            $sheet->getRowDimension($row++)->setRowHeight(32);
        }
        $totals = ['Daily present / Totals', ...$data['dailyPresent'], $data['totals']['present'], $data['totals']['leave'], $data['totals']['pending'], $data['totals']['workMinutes'] / 1440];
        foreach ($totals as $index => $value) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, $index === 0 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
        }
        $sheet->getStyle($last.($header + 1).':'.$last.$row)->getNumberFormat()->setFormatCode('[h]:mm');
        $this->styleTotalRow($sheet, 'A'.$row.':'.$last.$row);
        $this->drawGrid($sheet, 'A'.$header.':'.$last.$row);
        $sheet->getColumnDimension('A')->setWidth(38);
        for ($i = 2; $i <= count($data['days']) + 5; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($i <= count($data['days']) + 1 ? 4.5 : 11);
        }
        $sheet->getStyle('A'.$header.':'.$last.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B'.($header + 1).':'.$last.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('B'.($header + 1));
        $sheet->setAutoFilter('A'.$header.':'.$last.max($header, $row - 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($header, $header);

        return $book;
    }
}
