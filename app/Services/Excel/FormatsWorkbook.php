<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared chrome for the exported workbooks, so every file the system
 * produces carries the same header, table styling, and totals row.
 */
trait FormatsWorkbook
{
    private const HEADER_FILL = 'FF1B4B73';
    private const TOTAL_FILL = 'FFEDF0F3';
    private const WARNING_FILL = 'FFFCF1D8';
    private const GRID_COLOR = 'FFD5DCE3';

    private const MONEY_FORMAT = '#,##0.00';

    private function streamWorkbook(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Company name, report title, and the label/value rows beneath them.
     *
     * @param  array<string, string>  $meta
     * @return int  The next free row.
     */
    private function writeCompanyHeader(Worksheet $sheet, string $title, string $lastColumn, array $meta): int
    {
        $sheet->setCellValue('A1', 'Al Mohafiz Building Contracting LLC');
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', $title);
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->getStyle('A2')->getFont()->setSize(11)->getColor()->setARGB('FF46545F');

        $row = 4;

        foreach ($meta as $label => $value) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        return $row + 1;
    }

    /**
     * The label-over-value strip of headline numbers.
     *
     * @param  array<int, array{0: string, 1: mixed, 2: bool}>  $pairs  label, value, isMoney
     * @return int  The next free row.
     */
    private function writeStatStrip(Worksheet $sheet, array $pairs, int $row): int
    {
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

        $lastColumn = chr(ord('A') + count($pairs) - 1);

        $sheet->getStyle('A'.$row.':'.$lastColumn.($row + 1))
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::TOTAL_FILL);

        return $row + 3;
    }

    /**
     * @param  list<string>  $headings
     */
    private function writeTableHeadings(Worksheet $sheet, array $headings, int $row): void
    {
        $column = 'A';

        foreach ($headings as $heading) {
            $sheet->setCellValue($column.$row, $heading);
            $column++;
        }

        $lastColumn = chr(ord('A') + count($headings) - 1);

        $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::HEADER_FILL],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function styleTotalRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::TOTAL_FILL],
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function drawGrid(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)
            ->getColor()
            ->setARGB(self::GRID_COLOR);
    }

    /**
     * @param  array<string, int>  $widths
     */
    private function applyColumnWidths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function slugForFilename(string $value, string $fallback = 'export'): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
        $slug = trim((string) $slug, '-');

        return strtolower($slug !== '' ? $slug : $fallback);
    }
}
