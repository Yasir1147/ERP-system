<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('cheque_width_mm', 8, 2);
            $table->decimal('cheque_height_mm', 8, 2);
            $table->string('date_format', 20);
            $table->string('amount_figures_prefix')->nullable();
            $table->string('amount_figures_suffix')->nullable();
            $table->string('amount_words_prefix')->nullable();
            $table->string('amount_words_suffix')->nullable();
            $table->string('party_name_prefix')->nullable();
            $table->string('party_name_suffix')->nullable();
            $table->unsignedSmallInteger('party_name_max_length')->default(60);
            $table->unsignedSmallInteger('amount_words_max_length')->default(60);
            $table->string('account_payee_text')->default('A/C PAYEE ONLY');
            $table->string('label_1_text')->nullable();
            $table->string('label_2_text')->nullable();
            $table->string('signature_text')->default('Signature');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_formats');
    }
};
