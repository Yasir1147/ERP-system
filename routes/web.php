<?php

use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeLeaveController;
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

    return $request->user()->role === User::ROLE_ATTENDANCE
        ? redirect()->route('public-attendance.create')
        : redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::get('attendance', AttendanceReportController::class)->middleware(['auth', 'verified', 'role:admin'])->name('attendance.index');

Route::middleware(['attendance.access'])->group(function () {
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

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('employees/{type}', [EmployeeController::class, 'index'])
        ->whereIn('type', ['rope_access', 'contracting'])
        ->name('employees.type.index');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('projects/{type}', [ProjectController::class, 'index'])
        ->whereIn('type', ['rope_access', 'contracting'])
        ->name('projects.type.index');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('employee-leaves', EmployeeLeaveController::class)
        ->parameters(['employee-leaves' => 'employeeLeave'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/report', [PayrollController::class, 'report'])->name('payroll.report');
    Route::get('payroll/report-print', [PayrollController::class, 'reportPrint'])->name('payroll.report-print');
    Route::get('payroll/report/{employee}/ledger', [PayrollController::class, 'ledger'])->name('payroll.ledger');
    Route::get('payroll/report/{employee}/ledger-print', [PayrollController::class, 'ledgerPrint'])->name('payroll.ledger-print');
    Route::get('payroll/report/{employee}/ledger-export', [PayrollController::class, 'ledgerExport'])->name('payroll.ledger-export');
    Route::get('payroll/report/{employee}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
    Route::get('payroll/report/{employee}/payslip-export', [PayrollController::class, 'payslipExport'])->name('payroll.payslip-export');
    Route::put('payroll/settings/{employee}', [PayrollController::class, 'updateSetting'])->name('payroll.settings.update');
    Route::put('payroll/report/{employee}/adjustment', [PayrollController::class, 'updateAdjustment'])->name('payroll.adjustments.update');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

