<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('office_staff_attendance_sessions');

        Schema::create('office_staff_attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_staff_attendance_id');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->timestamps();

            $table->index(['office_staff_attendance_id', 'check_out_time'], 'office_att_sessions_open_idx');
            $table->foreign('office_staff_attendance_id', 'office_att_sessions_attendance_fk')
                ->references('id')
                ->on('office_staff_attendances')
                ->cascadeOnDelete();
        });

        DB::table('office_staff_attendances')
            ->whereNotNull('check_in_time')
            ->chunkById(100, function ($attendances) {
                foreach ($attendances as $attendance) {
                    DB::table('office_staff_attendance_sessions')->insert([
                        'office_staff_attendance_id' => $attendance->id,
                        'check_in_time' => $attendance->check_in_time,
                        'check_out_time' => $attendance->check_out_time,
                        'created_at' => $attendance->created_at,
                        'updated_at' => $attendance->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_staff_attendance_sessions');
    }
};
