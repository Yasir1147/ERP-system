<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->decimal('overtime_hours', 12, 8)->nullable()->change());
    }

    public function down(): void
    {
        // Keep the wider column so rollback never truncates recorded minutes.
    }
};
