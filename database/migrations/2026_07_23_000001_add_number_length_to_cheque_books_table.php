<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque_books', function (Blueprint $table) {
            $table->unsignedTinyInteger('number_length')->default(1)->after('end_number');
        });
    }

    public function down(): void
    {
        Schema::table('cheque_books', function (Blueprint $table) {
            $table->dropColumn('number_length');
        });
    }
};
