<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceTimesheetController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $selectedType = $this->normalizeType($request->query('type'));
        $month = $this->normalizeMonth($request->query('month'));
        $timesheet = $this->timesheetData($selectedType, $month);

        return Inertia::render('Attendance/Timesheet', [
            'dates' => $timesheet['dates'],
            'employees' => $timesheet['employees'],
            'filters' => [
                'type' => $selectedType,
                'month' => $month->format('Y-m'),
            ],
            'typeOptions' => collect(Employee::TYPES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $selectedType = $this->normalizeType($request->query('type'));
        $month = $this->normalizeMonth($request->query('month'));
        $timesheet = $this->timesheetData($selectedType, $month);
        $typeLabel = Employee::TYPES[$selectedType];
        $filename = 'timesheet-'.$selectedType.'-'.$month->format('Y-m').'.csv';

        $rows = [
            ['Al Mohafiz - Attendance Timesheet'],
            ['Employee Type', $typeLabel],
            ['Month', $month->format('F Y')],
            [],
            collect(['Employee Code', 'Employee Name', 'Profession'])
                ->merge($timesheet['dates']->map(fn (array $date) => $date['day'].' '.$date['weekday']))
                ->all(),
        ];

        foreach ($timesheet['employees'] as $employee) {
            $rows[] = collect([$employee['code'], $employee['name'], $employee['profession']])
                ->merge($employee['days']->map(fn (array $day) => $this->exportCell($day)))
                ->all();
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request): View
    {
        $selectedType = $this->normalizeType($request->query('type'));
        $month = $this->normalizeMonth($request->query('month'));
        $pageSize = $this->normalizePageSize($request->query('page_size'));
        $timesheet = $this->timesheetData($selectedType, $month);

        return view('attendance.timesheet-print', [
            'dates' => $timesheet['dates'],
            'employees' => $timesheet['employees'],
            'typeLabel' => Employee::TYPES[$selectedType],
            'monthLabel' => $month->format('F Y'),
            'filters' => [
                'type' => $selectedType,
                'month' => $month->format('Y-m'),
                'pageSize' => $pageSize,
            ],
            'page' => $pageSize === 'a4'
                ? ['label' => 'A4 Landscape', 'size' => 'A4', 'margin' => '5mm', 'font' => '5.8px', 'employeeWidth' => '32mm', 'cellHeight' => '8mm']
                : ['label' => 'A3 Landscape', 'size' => 'A3', 'margin' => '7mm', 'font' => '7.2px', 'employeeWidth' => '42mm', 'cellHeight' => '9mm'],
        ]);
    }

    private function timesheetData(string $selectedType, Carbon $month): array
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        $dates = collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'day' => $date->format('j'),
                'weekday' => $date->format('D'),
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
            ])
            ->values();

        $records = AttendanceRecord::query()
            ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->leftJoin('projects as overtime_projects', 'attendance_records.overtime_project_id', '=', 'overtime_projects.id')
            ->whereBetween('attendance_records.attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('employee', fn ($query) => $query->where('type', $selectedType))
            ->get([
                'attendance_records.employee_id',
                'attendance_records.status',
                'attendance_records.attendance_date',
                'attendance_records.leave_reason',
                'attendance_records.overtime_hours',
                'projects.name as project_name',
                'overtime_projects.name as overtime_project_name',
            ])
            ->groupBy(fn ($record) => $record->employee_id.'|'.Carbon::parse($record->attendance_date)->toDateString());

        $employeeIdsWithRecords = $records
            ->keys()
            ->map(fn (string $key) => (int) str($key)->before('|')->toString())
            ->unique()
            ->values();

        $employees = Employee::query()
            ->where('type', $selectedType)
            ->where(function ($query) use ($employeeIdsWithRecords) {
                $query->where('status', '!=', Employee::STATUS_LEFT);

                if ($employeeIdsWithRecords->isNotEmpty()) {
                    $query->orWhereIn('id', $employeeIdsWithRecords);
                }
            })
            ->orderByRaw('CAST(code AS UNSIGNED) asc')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'profession', 'status'])
            ->map(function (Employee $employee) use ($dates, $records) {
                return [
                    'id' => $employee->id,
                    'code' => $employee->code,
                    'name' => $employee->name,
                    'profession' => $employee->profession,
                    'status' => $employee->status,
                    'days' => $dates->map(function (array $date) use ($employee, $records) {
                        $record = $records->get($employee->id.'|'.$date['date'])?->first();

                        if (! $record) {
                            return [
                                'date' => $date['date'],
                                'status' => null,
                                'projectName' => null,
                                'overtimeProjectName' => null,
                                'overtimeHours' => null,
                                'leaveReason' => null,
                            ];
                        }

                        return [
                            'date' => $date['date'],
                            'status' => $record->status,
                            'projectName' => $record->project_name,
                            'overtimeProjectName' => $record->overtime_project_name ?: $record->project_name,
                            'overtimeHours' => $record->overtime_hours,
                            'leaveReason' => $record->leave_reason,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return [
            'dates' => $dates,
            'employees' => $employees,
        ];
    }

    private function exportCell(array $day): string
    {
        if (! $day['status']) {
            return '';
        }

        if ($day['status'] === AttendanceRecord::STATUS_PRESENT) {
            $overtimeLabel = $day['overtimeHours']
                ? ' (OT '.$day['overtimeHours'].'H'.($day['overtimeProjectName'] && $day['overtimeProjectName'] !== $day['projectName'] ? ' - '.$day['overtimeProjectName'] : '').')'
                : '';

            return trim(($day['projectName'] ?? 'Present').$overtimeLabel);
        }

        if ($day['status'] === AttendanceRecord::STATUS_ABSENT) {
            return 'Absent';
        }

        return trim('Leave'.($day['leaveReason'] ? ' - '.$day['leaveReason'] : ''));
    }

    private function normalizeType(mixed $type): string
    {
        if (! is_string($type) || ! array_key_exists($type, Employee::TYPES)) {
            return 'rope_access';
        }

        return $type;
    }

    private function normalizeMonth(mixed $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }

    private function normalizePageSize(mixed $pageSize): string
    {
        return $pageSize === 'a4' ? 'a4' : 'a3';
    }
}
