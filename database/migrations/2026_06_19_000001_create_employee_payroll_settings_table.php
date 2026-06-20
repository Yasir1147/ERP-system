<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('daily_salary', 12, 2)->default(0);
            $table->string('salary_rule')->default('present_days');
            $table->unsignedTinyInteger('standard_hours_per_day')->default(8);
            $table->boolean('is_overtime_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_settings');
    }
};
