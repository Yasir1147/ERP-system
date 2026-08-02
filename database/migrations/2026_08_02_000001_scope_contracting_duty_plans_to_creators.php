<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracting_duty_plans', function (Blueprint $table) {
            $table->dropUnique('contracting_duty_plans_duty_date_unique');
            $table->unique(['duty_date', 'created_by'], 'duty_plan_date_creator_unique');
        });

        Schema::table('contracting_duty_assignments', function (Blueprint $table) {
            $table->date('duty_date')->nullable()->after('contracting_duty_plan_id');
        });

        DB::table('contracting_duty_assignments')
            ->orderBy('id')
            ->each(function (object $assignment): void {
                $dutyDate = DB::table('contracting_duty_plans')
                    ->where('id', $assignment->contracting_duty_plan_id)
                    ->value('duty_date');

                DB::table('contracting_duty_assignments')
                    ->where('id', $assignment->id)
                    ->update(['duty_date' => $dutyDate]);
            });

        Schema::table('contracting_duty_assignments', function (Blueprint $table) {
            $table->date('duty_date')->nullable(false)->change();
            $table->unique(['duty_date', 'employee_id'], 'duty_date_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contracting_duty_assignments', function (Blueprint $table) {
            $table->dropUnique('duty_date_employee_unique');
            $table->dropColumn('duty_date');
        });

        Schema::table('contracting_duty_plans', function (Blueprint $table) {
            $table->dropUnique('duty_plan_date_creator_unique');
            $table->unique('duty_date');
        });
    }
};
