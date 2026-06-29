<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_fines', function (Blueprint $table) {
            $table->decimal('applied_amount', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('employee_fines', function (Blueprint $table) {
            $table->dropColumn('applied_amount');
        });
    }
};
