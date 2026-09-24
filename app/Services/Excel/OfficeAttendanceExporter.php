<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OfficeAttendanceExporter
{
    use FormatsWorkbook;

    public function download(array $data)
    {
        return $this->streamWorkbook($this->build($data), 'office-attendance-'.$this->slugForFilename($data['staffLabel']).'-'.$data['filters']['from'].'-to-'.$data['filters']['to'].'.xlsx');
    }

    public function build(array $data): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Office Attendance');
        $single = (bool) $data['selectedStaff'];
        $lastColumn = $single ? 'J' : 'L';
        $meta = ['Staff' => $data['staffLabel']];
        if ($single) {
            $meta['Designation'] = $data['selectedStaff']->designation ?: '-';
        }
        $meta['Period'] = $data['fromLabel'].' to '.$data['toLabel'];
        $meta['Work Mode'] = $data['workModes'][$data['filters']['workMode']] ?? 'All Modes';
        if ($data['filters']['search'] !== '') {
            $meta['Search'] = $data['filters']['search'];
        }
        $rules = $data['officeRules'];
        $meta['Office Rules'] = $rules['office_start_time'].' - '.$rules['office_end_time'].'; break '.($rules['break_included'] ? 'included' : 'deducted').'; grace '.$rules['late_grace_minutes'].' minutes';
        $meta['Fixed / Remote'] = 'Fixed and remote hours use recorded times without office break deductions.';
        $row = $this->writeCompanyHeader($sheet, 'Office Attendance Report', $lastColumn, $meta);
        $metaRow = 4;
        foreach ($meta as $value) {
            $sheet->setCellValueExplicit('B'.$metaRow, $value, DataType::TYPE_STRING);
            $sheet->mergeCells('B'.$metaRow.':'.$lastColumn.$metaRow);
            $sheet->getRowDimension($metaRow)->setRowHeight(30);
            $metaRow++;
        }
        $row = $this->writeStatStrip($sheet, [
            ['Staff', $data['summaryRows']->count(), false],
            ['Office Days', $data['summaryRows']->sum('officeDays'), false],
            ['Remote Days', $data['summaryRows']->sum('remoteDays'), false],
            ['Total Records', $data['attendanceRows']->count(), false],
            ['Work Hours', $data['reportTotals']['workLabel'], false],
            ['OT / Late', $data['reportTotals']['overtimeLabel'].' / '.$data['reportTotals']['lateCount'], false],
        ], $row);

        if (! $single) {
            $sheet->setCellValue('A'.$row++, 'Staff Summary');
            $this->writeTableHeadings($sheet, ['Staff', 'Designation', 'Type', 'Office Days', 'Remote Days', 'Total'], $row++);
            $start = $row;
            foreach ($data['summaryRows'] as $staff) {
                $sheet->getRowDimension($row)->setRowHeight(60);
                $this->writeValues($sheet, $row++, [$staff['code'].' - '.$staff['name'], $staff['designation'] ?: '-', $staff['staffTypeLabel'], $staff['officeDays'], $staff['remoteDays'], $staff['totalDays']]);
            }
            $this->drawGrid($sheet, 'A'.($start - 1).':F'.max($start - 1, $row - 1));
            $row += 2;
        }

        $sheet->setCellValue('A'.$row++, 'Attendance Detail');
        $headings = ['Date', ...($single ? [] : ['Staff', 'Designation']), 'Work Mode', 'Check In', 'Check Out', 'Sessions', 'Work Hrs', 'OT', 'Late', 'Note', 'Submitted By'];
        $header = $row;
        $this->writeTableHeadings($sheet, $headings, $row++);
        $start = $row;
        foreach ($data['attendanceRows'] as $attendance) {
            $values = [$attendance['dateLabel'], ...($single ? [] : [$attendance['staffCode'].' - '.$attendance['staffName'], $attendance['designation'] ?: '-']),
                $attendance['workModeLabel'], $attendance['checkInDisplay'] ?: '-', $attendance['checkOutDisplay'] ?: '-',
                $attendance['sessionDisplaySegments']->implode("\n") ?: '-', $attendance['workMinutes'] / 1440,
                $attendance['overtimeMinutes'] / 1440, $attendance['lateLabel'], $attendance['note'] ?: '-', $attendance['submittedBy'] ?: '-'];
            $this->writeValues($sheet, $row, $values);
            $noteLines = (int) ceil(mb_strlen($attendance['note'] ?: '') / 28);
            $sheet->getRowDimension($row)->setRowHeight(min(409, max(38, 16 * $attendance['sessionCount'], 15 * $noteLines)));
            $row++;
        }
        $end = $row - 1;
        $hoursColumn = $single ? 'F' : 'H';
        $otColumn = $single ? 'G' : 'I';
        $sheet->setCellValue('A'.$row, 'Total');
        foreach ([$hoursColumn, $otColumn] as $column) {
            $sheet->setCellValue($column.$row, $end >= $start ? '=SUM('.$column.$start.':'.$column.$end.')' : 0);
            $sheet->getStyle($column.$start.':'.$column.$row)->getNumberFormat()->setFormatCode('[h]:mm');
        }
        $this->styleTotalRow($sheet, 'A'.$row.':'.$lastColumn.$row);
        $this->drawGrid($sheet, 'A'.$header.':'.$lastColumn.$row);
        $sheet->setAutoFilter('A'.$header.':'.$lastColumn.max($header, $end));
        $sheet->freezePane('A'.($header + 1));
        $row += 3;

        if ($data['leaveRows']->isNotEmpty()) {
            $sheet->setCellValue('A'.$row++, 'Leave in Selected Date Range (not counted as work hours)');
            $sheet->mergeCells('A'.($row - 1).':'.$lastColumn.($row - 1));
            $this->writeTableHeadings($sheet, $single ? ['Date', 'Leave Status'] : ['Date', 'Staff', 'Leave Status'], $row++);
            foreach ($data['leaveRows'] as $leave) {
                $sheet->getRowDimension($row)->setRowHeight(45);
                $this->writeValues($sheet, $row++, [$leave['date'], ...($single ? [] : [$leave['staffName']]), $leave['label']]);
            }
        }
        foreach ($headings as $index => $heading) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth(match ($heading) {
                'Staff', 'Designation', 'Submitted By', 'Note', 'Sessions' => 30,
                'Late', 'Work Mode' => 22,
                default => 16,
            });
        }
        $sheet->getStyle('A1:'.$lastColumn.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($header, $header);
        $book->getProperties()->setTitle('Office Attendance Report')->setCompany('Al Mohafiz Building Contracting LLC');

        return $book;
    }

    private function writeValues(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
    }
}
