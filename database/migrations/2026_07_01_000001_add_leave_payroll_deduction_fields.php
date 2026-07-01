<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->string('payroll_deduction_status')->default('pending')->after('reason');
            $table->unsignedSmallInteger('payroll_deduct_days')->default(0)->after('payroll_deduction_status');
            $table->date('payroll_deduction_month')->nullable()->after('payroll_deduct_days');
            $table->text('payroll_deduction_note')->nullable()->after('payroll_deduction_month');
            $table->foreignId('payroll_deduction_reviewed_by')->nullable()->after('payroll_deduction_note')->constrained('users')->nullOnDelete();
            $table->timestamp('payroll_deduction_reviewed_at')->nullable()->after('payroll_deduction_reviewed_by');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('payroll_deduction_status')->default('pending')->after('leave_reason');
            $table->unsignedSmallInteger('payroll_deduct_days')->default(0)->after('payroll_deduction_status');
            $table->date('payroll_deduction_month')->nullable()->after('payroll_deduct_days');
            $table->text('payroll_deduction_note')->nullable()->after('payroll_deduction_month');
            $table->foreignId('payroll_deduction_reviewed_by')->nullable()->after('payroll_deduction_note')->constrained('users')->nullOnDelete();
            $table->timestamp('payroll_deduction_reviewed_at')->nullable()->after('payroll_deduction_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_deduction_reviewed_by');
            $table->dropColumn([
                'payroll_deduction_status',
                'payroll_deduct_days',
                'payroll_deduction_month',
                'payroll_deduction_note',
                'payroll_deduction_reviewed_at',
            ]);
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_deduction_reviewed_by');
            $table->dropColumn([
                'payroll_deduction_status',
                'payroll_deduct_days',
                'payroll_deduction_month',
                'payroll_deduction_note',
                'payroll_deduction_reviewed_at',
            ]);
        });
    }
};
