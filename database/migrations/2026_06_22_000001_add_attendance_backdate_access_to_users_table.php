<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('attendance_backdate_enabled')->default(false)->after('role');
            $table->date('attendance_backdate_from')->nullable()->after('attendance_backdate_enabled');
            $table->date('attendance_backdate_to')->nullable()->after('attendance_backdate_from');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_backdate_enabled',
                'attendance_backdate_from',
                'attendance_backdate_to',
            ]);
        });
    }
};
