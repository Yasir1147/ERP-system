<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->boolean('previous_balance_overridden')->default(false)->after('previous_balance');
        });

        DB::table('payroll_adjustments')
            ->where('previous_balance', '!=', 0)
            ->update(['previous_balance_overridden' => true]);
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropColumn('previous_balance_overridden');
        });
    }
};
