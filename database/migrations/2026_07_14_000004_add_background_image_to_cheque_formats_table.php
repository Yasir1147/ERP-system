<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->string('background_image_path')->nullable()->after('signature_text');
        });
    }

    public function down(): void
    {
        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->dropColumn('background_image_path');
        });
    }
};
