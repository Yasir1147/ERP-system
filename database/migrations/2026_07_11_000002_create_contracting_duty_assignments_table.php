<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracting_duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contracting_duty_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('planned');
            $table->boolean('has_overtime')->default(false);
            $table->unsignedTinyInteger('overtime_hours')->nullable();
            $table->foreignId('overtime_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contracting_duty_plan_id', 'employee_id'], 'duty_plan_employee_unique');
            $table->index(['status', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracting_duty_assignments');
    }
};
