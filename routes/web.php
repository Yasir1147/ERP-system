<?php

use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AttendanceTimesheetController;
use App\Http\Controllers\ContractingDutyPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExpenseController;
use App\Http\Controllers\EmployeeFineController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\OfficeAttendanceController;
use App\Http\Controllers\OfficeAttendanceReportController;
use App\Http\Controllers\OfficeStaffController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return redirect()->route('login');
    }

    if ($request->user()->role === User::ROLE_ATTENDANCE) {
        return $request->user()->defaultAttendanceType() === 'rope_access'
            ? redirect()->route('public-attendance.rope-access.create')
            : redirect()->route('public-attendance.contracting.create');
    }

    if ($request->user()->role === User::ROLE_OFFICE_STAFF) {
        return redirect()->route('office-attendance.staff.index');
    }

    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::get('attendance', AttendanceReportController::class)->middleware(['auth', 'verified', 'role:admin'])->name('attendance.index');
Route::put('attendance/{attendanceRecord}', [AttendanceReportController::class, 'update'])->middleware(['auth', 'verified', 'role:admin'])->name('attendance.update');
Route::delete('attendance/{attendanceRecord}', [AttendanceReportController::class, 'destroy'])->middleware(['auth', 'verified', 'role:admin'])->name('attendance.destroy');
Route::get('attendance/timesheet', AttendanceTimesheetController::class)->middleware(['auth', 'verified', 'role:admin'])->name('attendance.timesheet');
Route::get('attendance/timesheet-export', [AttendanceTimesheetController::class, 'export'])->middleware(['auth', 'verified', 'role:admin'])->name('attendance.timesheet.export');
Route::get('attendance/timesheet-print', [AttendanceTimesheetController::class, 'print'])->middleware(['auth', 'verified', 'role:admin'])->name('attendance.timesheet.print');

Route::middleware(['attendance.access'])->group(function () {
    Route::get('contracting-duty-plans', [ContractingDutyPlanController::class, 'index'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.index');
    Route::post('contracting-duty-plans/assignments', [ContractingDutyPlanController::class, 'storeAssignments'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.assignments.store');
    Route::put('contracting-duty-assignments/{assignment}', [ContractingDutyPlanController::class, 'updateAssignment'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.assignments.update');
    Route::delete('contracting-duty-assignments/{assignment}', [ContractingDutyPlanController::class, 'destroyAssignment'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.assignments.destroy');
    Route::post('contracting-duty-plans/{plan}/mark-present', [ContractingDutyPlanController::class, 'markPlannedPresent'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.mark-present');
    Route::post('contracting-duty-plans/{plan}/publish', [ContractingDutyPlanController::class, 'publish'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.publish');
    Route::post('contracting-duty-plans/{plan}/finalize', [ContractingDutyPlanController::class, 'finalize'])
        ->defaults('type', 'contracting')
        ->name('contracting-duties.finalize');
    Route::get('expenses/create', [EmployeeExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [EmployeeExpenseController::class, 'store'])->name('expenses.store');
    Route::get('fines/create', [EmployeeFineController::class, 'create'])->name('fines.create');
    Route::post('fines', [EmployeeFineController::class, 'store'])->name('fines.store');
    Route::get('mark-attendance', [PublicAttendanceController::class, 'create'])->name('public-attendance.create');
    Route::post('mark-attendance', [PublicAttendanceController::class, 'store'])->name('public-attendance.store');
    Route::get('mark-attendance/contracting', [PublicAttendanceController::class, 'create'])
        ->defaults('type', 'contracting')
        ->name('public-attendance.contracting.create');
    Route::post('mark-attendance/contracting', [PublicAttendanceController::class, 'store'])
        ->defaults('type', 'contracting')
        ->name('public-attendance.contracting.store');
    Route::get('mark-attendance/rope-access', [PublicAttendanceController::class, 'create'])
        ->defaults('type', 'rope_access')
        ->name('public-attendance.rope-access.create');
    Route::post('mark-attendance/rope-access', [PublicAttendanceController::class, 'store'])
        ->defaults('type', 'rope_access')
        ->name('public-attendance.rope-access.store');
});

Route::get('office-attendance/staff', [OfficeAttendanceController::class, 'index'])->name('office-attendance.staff.index');
Route::get('office-attendance/mark/{officeStaff}', [OfficeAttendanceController::class, 'create'])->name('office-attendance.staff.create');
Route::post('office-attendance/mark/{officeStaff}', [OfficeAttendanceController::class, 'store'])->name('office-attendance.staff.store');

Route::middleware(['auth', 'role:office_staff'])->group(function () {
    Route::get('office-attendance/mark', [OfficeAttendanceController::class, 'create'])->name('office-attendance.create');
    Route::post('office-attendance/mark', [OfficeAttendanceController::class, 'store'])->name('office-attendance.store');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('employees/{type}', [EmployeeController::class, 'index'])
        ->whereIn('type', ['rope_access', 'contracting'])
        ->name('employees.type.index');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('projects/overview', [ProjectController::class, 'overview'])->name('projects.overview');
    Route::get('projects/{project}/employee-history', [ProjectController::class, 'employeeHistory'])->name('projects.employee-history');
    Route::get('projects/{type}', [ProjectController::class, 'index'])
        ->whereIn('type', ['rope_access', 'contracting'])
        ->name('projects.type.index');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('employee-leaves', EmployeeLeaveController::class)
        ->parameters(['employee-leaves' => 'employeeLeave'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::put('employee-leaves/{employeeLeave}/deduction', [EmployeeLeaveController::class, 'applyDeduction'])
        ->name('employee-leaves.deduction.apply');
    Route::put('employee-leaves/{employeeLeave}/deduction/waive', [EmployeeLeaveController::class, 'waiveDeduction'])
        ->name('employee-leaves.deduction.waive');
    Route::put('employee-leaves/attendance/{attendanceRecord}', [EmployeeLeaveController::class, 'updateDailyLeave'])
        ->name('employee-leaves.daily.update');
    Route::delete('employee-leaves/attendance/{attendanceRecord}', [EmployeeLeaveController::class, 'destroyDailyLeave'])
        ->name('employee-leaves.daily.destroy');
    Route::put('employee-leaves/attendance/{attendanceRecord}/deduction', [EmployeeLeaveController::class, 'applyDailyLeaveDeduction'])
        ->name('employee-leaves.daily.deduction.apply');
    Route::put('employee-leaves/attendance/{attendanceRecord}/deduction/waive', [EmployeeLeaveController::class, 'waiveDailyLeaveDeduction'])
        ->name('employee-leaves.daily.deduction.waive');
    Route::get('fines', [EmployeeFineController::class, 'index'])->name('fines.index');
    Route::post('fines/{employeeFine}/apply', [EmployeeFineController::class, 'apply'])->name('fines.apply');
    Route::post('fines/{employeeFine}/waive', [EmployeeFineController::class, 'waive'])->name('fines.waive');
    Route::get('expenses', [EmployeeExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses/{employeeExpense}/approve', [EmployeeExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{employeeExpense}/reject', [EmployeeExpenseController::class, 'reject'])->name('expenses.reject');
    Route::delete('expenses/{employeeExpense}', [EmployeeExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::resource('office-staff', OfficeStaffController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('office-attendance/report', [OfficeAttendanceReportController::class, 'index'])->name('office-attendance.report');
    Route::get('office-attendance/report/{officeStaff}/details', [OfficeAttendanceReportController::class, 'details'])->name('office-attendance.details');
    Route::put('office-attendance/report/{officeAttendance}', [OfficeAttendanceReportController::class, 'update'])->name('office-attendance.update');
    Route::post('office-attendance/rules', [OfficeAttendanceReportController::class, 'updateRules'])->name('office-attendance.rules.update');
    Route::get('office-attendance/report-print', [OfficeAttendanceReportController::class, 'print'])->name('office-attendance.report.print');
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/report', [PayrollController::class, 'report'])->name('payroll.report');
    Route::get('payroll/report-print', [PayrollController::class, 'reportPrint'])->name('payroll.report-print');
    Route::get('payroll/report/payslips', [PayrollController::class, 'bulkPayslips'])->name('payroll.payslips.bulk');
    Route::post('payroll/report/adjustments-bulk', [PayrollController::class, 'updateAdjustmentsBulk'])->name('payroll.adjustments.bulk');
    Route::get('payroll/report/{employee}/ledger', [PayrollController::class, 'ledger'])->name('payroll.ledger');
    Route::get('payroll/report/{employee}/ledger-print', [PayrollController::class, 'ledgerPrint'])->name('payroll.ledger-print');
    Route::get('payroll/report/{employee}/ledger-export', [PayrollController::class, 'ledgerExport'])->name('payroll.ledger-export');
    Route::get('payroll/report/{employee}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
    Route::get('payroll/report/{employee}/payslip-export', [PayrollController::class, 'payslipExport'])->name('payroll.payslip-export');
    Route::put('payroll/absence-rule', [PayrollController::class, 'updateAbsenceRule'])->name('payroll.absence-rule.update');
    Route::put('payroll/settings/{employee}', [PayrollController::class, 'updateSetting'])->name('payroll.settings.update');
    Route::put('payroll/report/{employee}/adjustment', [PayrollController::class, 'updateAdjustment'])->name('payroll.adjustments.update');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
