<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $selectedDate = $request->date('date')?->toDateString() ?? Carbon::today()->toDateString();
        $selectedType = $this->normalizeType($request->query('type'));
        $selectedDay = Carbon::parse($selectedDate);
        $monthStart = $selectedDay->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedDay->copy()->endOfMonth()->toDateString();

        $dateRecords = AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('attendance_records.attendance_date', $selectedDate)
            ->when($selectedType, fn ($query) => $query->where('employees.type', $selectedType));

        $manualLeaveEmployeeIds = (clone $dateRecords)
            ->where('attendance_records.status', AttendanceRecord::STATUS_LEAVE)
            ->distinct()
            ->pluck('attendance_records.employee_id');
        $rangeLeaveEmployeeIds = EmployeeLeave::query()
            ->join('employees', 'employee_leaves.employee_id', '=', 'employees.id')
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('employee_leaves.start_date', '<=', $selectedDate)
            ->whereDate('employee_leaves.end_date', '>=', $selectedDate)
            ->when($selectedType, fn ($query) => $query->where('employees.type', $selectedType))
            ->distinct()
            ->pluck('employee_leaves.employee_id');
        $leaveEmployeeIds = $manualLeaveEmployeeIds->merge($rangeLeaveEmployeeIds)->unique()->values();

        $summary = [
            'presentToday' => (clone $dateRecords)
                ->where('attendance_records.status', AttendanceRecord::STATUS_PRESENT)
                ->distinct('attendance_records.employee_id')
                ->count('attendance_records.employee_id'),
            'absentToday' => (clone $dateRecords)
                ->where('attendance_records.status', AttendanceRecord::STATUS_ABSENT)
                ->distinct('attendance_records.employee_id')
                ->count('attendance_records.employee_id'),
            'leaveToday' => $leaveEmployeeIds->count(),
            'totalEmployees' => Employee::query()
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->when($selectedType, fn ($query) => $query->where('type', $selectedType))
                ->count(),
        ];

        $projectAttendance = AttendanceRecord::query()
            ->join('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('attendance_records.attendance_date', $selectedDate)
            ->where('attendance_records.status', AttendanceRecord::STATUS_PRESENT)
            ->when($selectedType, fn ($query) => $query->where('employees.type', $selectedType))
            ->select([
                'projects.id',
                'projects.name',
                'projects.type',
                DB::raw('COUNT(DISTINCT attendance_records.employee_id) as employee_count'),
            ])
            ->groupBy('projects.id', 'projects.name', 'projects.type')
            ->orderByDesc('employee_count')
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'type' => str_replace('_', ' ', $project->type),
                'employeeCount' => (int) $project->employee_count,
            ]);

        $markedEmployeeIds = AttendanceRecord::query()
            ->whereDate('attendance_date', $selectedDate)
            ->pluck('employee_id');

        $attendanceRecords = AttendanceRecord::query()
            ->leftJoin('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->leftJoin('projects as overtime_projects', 'attendance_records.overtime_project_id', '=', 'overtime_projects.id')
            ->leftJoin('users', 'attendance_records.submitted_by', '=', 'users.id')
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('attendance_records.attendance_date', $selectedDate)
            ->when($selectedType, fn ($query) => $query->where('employees.type', $selectedType))
            ->orderBy('employees.type')
            ->orderBy('employees.name')
            ->get([
                'attendance_records.id',
                'attendance_records.status',
                'attendance_records.attendance_date',
                'attendance_records.leave_reason',
                'attendance_records.overtime_hours',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
                'employees.type as employee_type',
                'projects.name as project_name',
                'overtime_projects.name as overtime_project_name',
                'users.name as submitted_by_name',
                'users.role as submitted_by_role',
            ])
            ->map(fn ($record) => [
                'id' => $record->id,
                'employeeCode' => $record->employee_code,
                'employeeName' => $record->employee_name,
                'employeeProfession' => $record->employee_profession,
                'employeeType' => $record->employee_type,
                'projectName' => $record->project_name,
                'overtimeProjectName' => $record->overtime_project_name ?: $record->project_name,
                'status' => $record->status,
                'date' => Carbon::parse($record->attendance_date)->format('d/m/Y'),
                'leaveReason' => $record->leave_reason,
                'overtimeHours' => $record->overtime_hours,
                'submittedBy' => $record->submitted_by_name,
                'submittedByRole' => $record->submitted_by_role,
            ])
            ->values();

        $leaveRangeRecords = EmployeeLeave::query()
            ->join('employees', 'employee_leaves.employee_id', '=', 'employees.id')
            ->leftJoin('users', 'employee_leaves.created_by', '=', 'users.id')
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('employee_leaves.start_date', '<=', $selectedDate)
            ->whereDate('employee_leaves.end_date', '>=', $selectedDate)
            ->whereNotIn('employee_leaves.employee_id', $markedEmployeeIds)
            ->when($selectedType, fn ($query) => $query->where('employees.type', $selectedType))
            ->orderBy('employees.type')
            ->orderBy('employees.name')
            ->get([
                'employee_leaves.id',
                'employee_leaves.reason',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
                'employees.type as employee_type',
                'users.name as submitted_by_name',
                'users.role as submitted_by_role',
            ])
            ->map(fn ($record) => [
                'id' => 'leave-'.$record->id,
                'employeeCode' => $record->employee_code,
                'employeeName' => $record->employee_name,
                'employeeProfession' => $record->employee_profession,
                'employeeType' => $record->employee_type,
                'projectName' => null,
                'status' => AttendanceRecord::STATUS_LEAVE,
                'date' => $selectedDay->format('d/m/Y'),
                'leaveReason' => $record->reason ?: 'Leave',
                'overtimeHours' => null,
                'submittedBy' => $record->submitted_by_name,
                'submittedByRole' => $record->submitted_by_role,
            ])
            ->values();

        $attendanceRecords = $attendanceRecords
            ->toBase()
            ->merge($leaveRangeRecords->toBase())
            ->groupBy('employeeType')
            ->map(fn ($records) => $records->values());

        $monthlySummary = collect(Employee::TYPES)
            ->when($selectedType, fn ($types) => $types->only($selectedType))
            ->map(fn ($label, $type) => [
                'type' => $type,
                'label' => $label,
                'totalEmployees' => Employee::where('type', $type)->where('status', '!=', Employee::STATUS_LEFT)->count(),
                'present' => $this->monthlyStatusCount($type, AttendanceRecord::STATUS_PRESENT, $monthStart, $monthEnd),
                'absent' => $this->monthlyStatusCount($type, AttendanceRecord::STATUS_ABSENT, $monthStart, $monthEnd),
                'leave' => $this->monthlyLeaveRecordCount($type, $monthStart, $monthEnd),
            ])
            ->values();

        $completedLongLeaves = EmployeeLeave::query()
            ->with('employee:id,code,name,profession,type,status')
            ->whereDate('end_date', '<=', $selectedDate)
            ->when($selectedType, fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('type', $selectedType)))
            ->latest('end_date')
            ->get()
            ->filter(fn (EmployeeLeave $leave) => $leave->start_date->diffInDays($leave->end_date) + 1 > 3)
            ->filter(fn (EmployeeLeave $leave) => $leave->employee?->status !== Employee::STATUS_LEFT)
            ->map(fn (EmployeeLeave $leave) => [
                'id' => $leave->id,
                'employeeCode' => $leave->employee?->code,
                'employeeName' => $leave->employee?->name,
                'employeeProfession' => $leave->employee?->profession,
                'employeeType' => $leave->employee?->type,
                'employeeStatus' => $leave->employee?->status,
                'startDateLabel' => $leave->start_date->format('d/m/Y'),
                'endDateLabel' => $leave->end_date->format('d/m/Y'),
                'durationDays' => $leave->start_date->diffInDays($leave->end_date) + 1,
                'reason' => $leave->reason,
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'projectAttendance' => $projectAttendance,
            'attendanceRecords' => [
                'rope_access' => $attendanceRecords->get('rope_access', collect())->values(),
                'contracting' => $attendanceRecords->get('contracting', collect())->values(),
            ],
            'monthlySummary' => $monthlySummary,
            'completedLongLeaves' => $completedLongLeaves,
            'selectedDate' => $selectedDate,
            'selectedDateLabel' => $selectedDay->format('d/m/Y'),
            'selectedMonthLabel' => $selectedDay->format('F Y'),
            'selectedType' => $selectedType ?? 'all',
            'typeOptions' => collect(['all' => 'All Projects'])->merge(Employee::TYPES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
        ]);
    }

    private function monthlyStatusCount(string $employeeType, string $status, string $monthStart, string $monthEnd): int
    {
        return AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('employees.type', $employeeType)
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->where('attendance_records.status', $status)
            ->whereBetween('attendance_records.attendance_date', [$monthStart, $monthEnd])
            ->count();
    }

    private function monthlyLeaveRecordCount(string $employeeType, string $monthStart, string $monthEnd): int
    {
        $dailyLeaveCount = AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('employees.type', $employeeType)
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->where('attendance_records.status', AttendanceRecord::STATUS_LEAVE)
            ->whereBetween('attendance_records.attendance_date', [$monthStart, $monthEnd])
            ->count();

        $longLeaveCount = EmployeeLeave::query()
            ->join('employees', 'employee_leaves.employee_id', '=', 'employees.id')
            ->where('employees.type', $employeeType)
            ->where('employees.status', '!=', Employee::STATUS_LEFT)
            ->whereDate('employee_leaves.start_date', '<=', $monthEnd)
            ->whereDate('employee_leaves.end_date', '>=', $monthStart)
            ->count();

        return $dailyLeaveCount + $longLeaveCount;
    }

    private function normalizeType(mixed $type): ?string
    {
        if (! is_string($type) || $type === '' || $type === 'all') {
            return null;
        }

        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return $type;
    }
}
