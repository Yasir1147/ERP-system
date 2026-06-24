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
            $table->foreignId('overtime_project_id')
                ->nullable()
                ->after('project_id')
                ->constrained('projects')
                ->nullOnDelete();
        });

        DB::table('attendance_records')
            ->where('has_overtime', true)
            ->whereNotNull('project_id')
            ->whereNull('overtime_project_id')
            ->update(['overtime_project_id' => DB::raw('project_id')]);
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overtime_project_id');
        });
    }
};
