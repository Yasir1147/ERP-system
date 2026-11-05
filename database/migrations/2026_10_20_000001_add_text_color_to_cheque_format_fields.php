<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque_format_fields', function (Blueprint $table) {
            $table->string('text_color', 7)->default('#000000');
        });
    }

    public function down(): void
    {
        Schema::table('cheque_format_fields', function (Blueprint $table) {
            $table->dropColumn('text_color');
        });
    }
};
