<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Services\Excel\FormatsWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee register for one employee type, as a formatted workbook.
 */
class EmployeeListExporter
{
    use FormatsWorkbook;

    /**
     * @param  array<string, mixed>  $data
     */
    public function download(array $data): StreamedResponse
    {
        return $this->streamWorkbook(
            $this->build($data),
            'employees-'.$this->slugForFilename($data['typeLabel']).'-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function build(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employees');

        $row = $this->writeCompanyHeader($sheet, 'Employee List', 'E', [
            'Category' => $data['typeLabel'],
            'Generated' => now()->format('d/m/Y h:i A'),
        ]);

        $counts = $data['counts'];

        $row = $this->writeStatStrip($sheet, [
            ['Total', $counts['total'], false],
            ['Active', $counts['active'], false],
            ['On Leave', $counts['on_leave'], false],
            ['Left', $counts['left'], false],
        ], $row);

        $this->writeTable($sheet, $data['employees'], $row);

        $this->applyColumnWidths($sheet, [
            'A' => 12,
            'B' => 34,
            'C' => 26,
            'D' => 14,
            'E' => 16,
        ]);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $spreadsheet->getProperties()
            ->setTitle('Employee List')
            ->setSubject($data['typeLabel'])
            ->setCompany('Al Mohafiz Building Contracting LLC');

        return $spreadsheet;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $employees
     */
    private function writeTable(Worksheet $sheet, Collection $employees, int $row): void
    {
        $headerRow = $row;

        $this->writeTableHeadings($sheet, ['Code', 'Name', 'Profession', 'Status', 'Added On'], $headerRow);

        $row = $headerRow + 1;

        foreach ($employees as $employee) {
            $sheet->setCellValue('A'.$row, $employee['code']);
            $sheet->setCellValue('B'.$row, $employee['name']);
            $sheet->setCellValue('C'.$row, $employee['profession']);
            $sheet->setCellValue('D'.$row, $employee['statusLabel']);
            $sheet->setCellValue('E'.$row, $employee['addedOn']);

            // Codes are numeric strings like "0142"; Excel would drop the
            // leading zero if the cell were treated as a number.
            $sheet->getStyle('A'.$row)
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            $row++;
        }

        $lastDataRow = $row - 1;

        if ($lastDataRow >= $headerRow + 1) {
            $sheet->setAutoFilter('A'.$headerRow.':E'.$lastDataRow);
            $this->drawGrid($sheet, 'A'.$headerRow.':E'.$lastDataRow);
        }

        $sheet->freezePane('A'.($headerRow + 1));
    }

    /**
     * Shapes an employee list for both the workbook and the print view.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, mixed>
     */
    public static function present(Collection $employees, string $type): array
    {
        $rows = $employees->map(fn (Employee $employee) => [
            'code' => $employee->code,
            'name' => $employee->name,
            'profession' => $employee->profession ?: '-',
            'status' => $employee->status,
            'statusLabel' => Employee::STATUSES[$employee->status] ?? $employee->status,
            'addedOn' => $employee->created_at?->format('d/m/Y') ?? '-',
        ])->values();

        return [
            'type' => $type,
            'typeLabel' => Employee::TYPES[$type],
            'employees' => $rows,
            'counts' => [
                'total' => $rows->count(),
                'active' => $rows->where('status', Employee::STATUS_ACTIVE)->count(),
                'on_leave' => $rows->where('status', Employee::STATUS_ON_LEAVE)->count(),
                'left' => $rows->where('status', Employee::STATUS_LEFT)->count(),
            ],
        ];
    }
}
