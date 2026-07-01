<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLeaveController extends Controller
{
    public function index(): Response
    {
        $longLeaves = EmployeeLeave::query()
            ->with([
                'employee:id,name,profession,type,status',
                'creator:id,name,role',
                'payrollDeductionReviewer:id,name,role',
            ])
            ->latest('start_date')
            ->get()
            ->map(fn (EmployeeLeave $leave) => [
                'id' => $leave->id,
                'source' => 'long_leave',
                'canEdit' => true,
                'employeeId' => $leave->employee_id,
                'employeeName' => $leave->employee?->name,
                'employeeProfession' => $leave->employee?->profession,
                'employeeType' => $leave->employee?->type,
                'employeeStatus' => $leave->employee?->status,
                'startDate' => $leave->start_date->toDateString(),
                'endDate' => $leave->end_date->toDateString(),
                'startDateLabel' => $leave->start_date->format('d/m/Y'),
                'endDateLabel' => $leave->end_date->format('d/m/Y'),
                'durationDays' => $leave->start_date->diffInDays($leave->end_date) + 1,
                'reason' => $leave->reason,
                'createdBy' => $leave->creator?->name,
                'createdByRole' => $leave->creator?->role,
                'payrollDeductionStatus' => $leave->payroll_deduction_status,
                'payrollDeductDays' => (int) $leave->payroll_deduct_days,
                'payrollDeductionMonth' => $leave->payroll_deduction_month?->format('Y-m'),
                'payrollDeductionMonthLabel' => $leave->payroll_deduction_month?->format('F Y'),
                'payrollDeductionNote' => $leave->payroll_deduction_note,
                'payrollDeductionReviewedBy' => $leave->payrollDeductionReviewer?->name,
                'payrollDeductionReviewedAtLabel' => $leave->payroll_deduction_reviewed_at?->format('d/m/Y h:i A'),
            ]);

        $dailyLeaves = AttendanceRecord::query()
            ->with([
                'employee:id,name,profession,type,status',
                'submitter:id,name,role',
                'payrollDeductionReviewer:id,name,role',
            ])
            ->where('status', AttendanceRecord::STATUS_LEAVE)
            ->latest('attendance_date')
            ->get()
            ->map(function (AttendanceRecord $record) {
                $date = Carbon::parse($record->attendance_date);

                return [
                    'id' => $record->id,
                    'source' => 'daily_leave',
                    'canEdit' => true,
                    'employeeId' => $record->employee_id,
                    'employeeName' => $record->employee?->name,
                    'employeeProfession' => $record->employee?->profession,
                    'employeeType' => $record->employee?->type,
                    'employeeStatus' => $record->employee?->status,
                    'startDate' => $date->toDateString(),
                    'endDate' => $date->toDateString(),
                    'startDateLabel' => $date->format('d/m/Y'),
                    'endDateLabel' => $date->format('d/m/Y'),
                    'durationDays' => 1,
                    'reason' => $record->leave_reason,
                    'createdBy' => $record->submitter?->name,
                    'createdByRole' => $record->submitter?->role,
                    'payrollDeductionStatus' => $record->payroll_deduction_status,
                    'payrollDeductDays' => (int) $record->payroll_deduct_days,
                    'payrollDeductionMonth' => $record->payroll_deduction_month?->format('Y-m'),
                    'payrollDeductionMonthLabel' => $record->payroll_deduction_month?->format('F Y'),
                    'payrollDeductionNote' => $record->payroll_deduction_note,
                    'payrollDeductionReviewedBy' => $record->payrollDeductionReviewer?->name,
                    'payrollDeductionReviewedAtLabel' => $record->payroll_deduction_reviewed_at?->format('d/m/Y h:i A'),
                ];
            });

        $dailyAbsents = AttendanceRecord::query()
            ->with([
                'employee:id,name,profession,type,status',
                'submitter:id,name,role',
            ])
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->latest('attendance_date')
            ->get()
            ->map(function (AttendanceRecord $record) {
                $date = Carbon::parse($record->attendance_date);

                return [
                    'id' => $record->id,
                    'source' => 'daily_absent',
                    'canEdit' => false,
                    'employeeId' => $record->employee_id,
                    'employeeName' => $record->employee?->name,
                    'employeeProfession' => $record->employee?->profession,
                    'employeeType' => $record->employee?->type,
                    'employeeStatus' => $record->employee?->status,
                    'startDate' => $date->toDateString(),
                    'endDate' => $date->toDateString(),
                    'startDateLabel' => $date->format('d/m/Y'),
                    'endDateLabel' => $date->format('d/m/Y'),
                    'durationDays' => 1,
                    'reason' => 'Absent',
                    'createdBy' => $record->submitter?->name,
                    'createdByRole' => $record->submitter?->role,
                    'payrollDeductionStatus' => 'attendance_absent',
                    'payrollDeductDays' => 1,
                    'payrollDeductionMonth' => $date->format('Y-m'),
                    'payrollDeductionMonthLabel' => $date->format('F Y'),
                    'payrollDeductionNote' => null,
                    'payrollDeductionReviewedBy' => null,
                    'payrollDeductionReviewedAtLabel' => null,
                ];
            });

        return Inertia::render('EmployeeLeaves/Index', [
            'employees' => Employee::query()
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'profession', 'type', 'status']),
            'employeeTypes' => Employee::TYPES,
            'leaves' => $longLeaves
                ->merge($dailyLeaves)
                ->merge($dailyAbsents)
                ->sortByDesc('startDate')
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureNoOverlap($data['employee_id'], $data['start_date'], $data['end_date']);

        $data['created_by'] = $request->user()->id;

        EmployeeLeave::create($data);

        return to_route('employee-leaves.index');
    }

    public function update(Request $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureNoOverlap($data['employee_id'], $data['start_date'], $data['end_date'], $employeeLeave->id);

        $employeeLeave->update($data);

        return to_route('employee-leaves.index');
    }

    public function destroy(EmployeeLeave $employeeLeave): RedirectResponse
    {
        $employeeLeave->delete();

        return to_route('employee-leaves.index');
    }

    public function updateDailyLeave(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        abort_unless($attendanceRecord->status === AttendanceRecord::STATUS_LEAVE, 404);

        $data = $this->validatedDailyLeaveData($request);
        $this->ensureNoAttendanceOverlap($data['employee_id'], $data['start_date'], $attendanceRecord->id);

        $attendanceRecord->update([
            'employee_id' => $data['employee_id'],
            'attendance_date' => $data['start_date'],
            'leave_reason' => $data['reason'],
            'project_id' => null,
            'overtime_project_id' => null,
            'has_overtime' => false,
            'overtime_hours' => null,
        ]);

        return to_route('employee-leaves.index');
    }

    public function destroyDailyLeave(AttendanceRecord $attendanceRecord): RedirectResponse
    {
        abort_unless($attendanceRecord->status === AttendanceRecord::STATUS_LEAVE, 404);

        $attendanceRecord->delete();

        return to_route('employee-leaves.index');
    }

    public function applyDeduction(Request $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $data = $this->validatedDeductionData($request, $employeeLeave->start_date->diffInDays($employeeLeave->end_date) + 1);

        $employeeLeave->forceFill([
            'payroll_deduction_status' => EmployeeLeave::PAYROLL_DEDUCTION_APPLIED,
            'payroll_deduct_days' => (int) $data['payroll_deduct_days'],
            'payroll_deduction_month' => Carbon::createFromFormat('Y-m', $data['payroll_deduction_month'])->startOfMonth()->toDateString(),
            'payroll_deduction_note' => $data['payroll_deduction_note'] ?? null,
            'payroll_deduction_reviewed_by' => $request->user()?->id,
            'payroll_deduction_reviewed_at' => now(),
        ])->save();

        return to_route('employee-leaves.index')->with('success', 'Leave deduction applied as absent days.');
    }

    public function waiveDeduction(Request $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $employeeLeave->forceFill([
            'payroll_deduction_status' => EmployeeLeave::PAYROLL_DEDUCTION_WAIVED,
            'payroll_deduct_days' => 0,
            'payroll_deduction_month' => null,
            'payroll_deduction_note' => $request->string('payroll_deduction_note')->trim()->value() ?: null,
            'payroll_deduction_reviewed_by' => $request->user()?->id,
            'payroll_deduction_reviewed_at' => now(),
        ])->save();

        return to_route('employee-leaves.index')->with('success', 'Leave deduction waived.');
    }

    public function applyDailyLeaveDeduction(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        abort_unless($attendanceRecord->status === AttendanceRecord::STATUS_LEAVE, 404);

        $data = $this->validatedDeductionData($request, 1);

        $attendanceRecord->forceFill([
            'payroll_deduction_status' => AttendanceRecord::PAYROLL_DEDUCTION_APPLIED,
            'payroll_deduct_days' => (int) $data['payroll_deduct_days'],
            'payroll_deduction_month' => Carbon::createFromFormat('Y-m', $data['payroll_deduction_month'])->startOfMonth()->toDateString(),
            'payroll_deduction_note' => $data['payroll_deduction_note'] ?? null,
            'payroll_deduction_reviewed_by' => $request->user()?->id,
            'payroll_deduction_reviewed_at' => now(),
        ])->save();

        return to_route('employee-leaves.index')->with('success', 'Daily leave deduction applied as absent days.');
    }

    public function waiveDailyLeaveDeduction(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        abort_unless($attendanceRecord->status === AttendanceRecord::STATUS_LEAVE, 404);

        $attendanceRecord->forceFill([
            'payroll_deduction_status' => AttendanceRecord::PAYROLL_DEDUCTION_WAIVED,
            'payroll_deduct_days' => 0,
            'payroll_deduction_month' => null,
            'payroll_deduction_note' => $request->string('payroll_deduction_note')->trim()->value() ?: null,
            'payroll_deduction_reviewed_by' => $request->user()?->id,
            'payroll_deduction_reviewed_at' => now(),
        ])->save();

        return to_route('employee-leaves.index')->with('success', 'Daily leave deduction waived.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedDailyLeaveData(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'start_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['end_date'] = $data['start_date'];

        return $data;
    }

    private function validatedDeductionData(Request $request, int $durationDays): array
    {
        return $request->validate([
            'payroll_deduct_days' => ['required', 'integer', 'min:1', 'max:'.$durationDays],
            'payroll_deduction_month' => ['required', 'date_format:Y-m'],
            'payroll_deduction_note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function ensureNoOverlap(int $employeeId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $hasOverlap = EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_date' => 'This employee already has leave in the selected date range.',
            ]);
        }
    }

    private function ensureNoAttendanceOverlap(int $employeeId, string $date, ?int $ignoreId = null): void
    {
        $alreadyMarked = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($alreadyMarked) {
            throw ValidationException::withMessages([
                'start_date' => 'Attendance for this employee is already marked on the selected date.',
            ]);
        }
    }
}
