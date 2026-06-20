<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status', 20)->default('present')->after('employee_id');
            $table->text('leave_reason')->nullable()->after('status');
        });

        DB::statement('ALTER TABLE attendance_records MODIFY project_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::table('attendance_records')->whereNull('project_id')->delete();
        DB::statement('ALTER TABLE attendance_records MODIFY project_id BIGINT UNSIGNED NOT NULL');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['status', 'leave_reason']);
        });
    }
};
