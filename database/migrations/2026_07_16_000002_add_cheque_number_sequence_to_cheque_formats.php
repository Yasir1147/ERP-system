<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->unsignedBigInteger('next_cheque_number')->nullable()->after('logo_image_path');
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->unique(['cheque_format_id', 'cheque_number'], 'cheques_format_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropUnique('cheques_format_number_unique');
        });

        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->dropColumn('next_cheque_number');
        });
    }
};
