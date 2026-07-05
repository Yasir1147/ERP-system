<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('staff_type')->default('on_site')->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('office_staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_staff_id')->constrained('office_staff')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->string('work_mode')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['office_staff_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_staff_attendances');
        Schema::dropIfExists('office_staff');
    }
};
