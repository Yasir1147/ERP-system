<?php

use App\Models\EmployeePayrollSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_settings', function (Blueprint $table) {
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('daily_salary');
        });

        DB::table('employee_payroll_settings')
            ->where('salary_rule', EmployeePayrollSetting::RULE_FIXED_30_DAYS)
            ->whereNull('monthly_salary')
            ->update([
                'monthly_salary' => DB::raw('ROUND(daily_salary * 30, 0)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('employee_payroll_settings', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });
    }
};
