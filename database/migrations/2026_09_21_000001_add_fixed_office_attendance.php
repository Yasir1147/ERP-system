<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_staff', function (Blueprint $table) {
            $table->string('attendance_mode')->default('sessions');
            $table->time('fixed_start_time')->default('09:00:00');
            $table->time('fixed_end_time')->default('17:00:00');
        });
        Schema::table('office_staff_attendances', function (Blueprint $table) {
            $table->boolean('is_fixed')->default(false);
            $table->timestamp('marked_at')->nullable();
        });
        Schema::create('office_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_staff_id')->constrained('office_staff')->cascadeOnDelete();
            $table->date('leave_date');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->string('email_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['office_staff_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_leave_requests');
        Schema::table('office_staff_attendances', fn (Blueprint $table) => $table->dropColumn(['is_fixed', 'marked_at']));
        Schema::table('office_staff', fn (Blueprint $table) => $table->dropColumn(['attendance_mode', 'fixed_start_time', 'fixed_end_time']));
    }
};
