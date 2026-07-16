<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_format_id')->constrained()->restrictOnDelete();
            $table->foreignId('cheque_party_id')->constrained()->restrictOnDelete();
            $table->string('cheque_number')->nullable()->index();
            $table->date('cheque_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('payee_name');
            $table->text('amount_in_words');
            $table->string('account_payee_text')->nullable();
            $table->string('signature_text')->nullable();
            $table->string('label_1_text')->nullable();
            $table->string('label_2_text')->nullable();
            $table->string('voucher_number')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('prepared')->index();
            $table->json('format_snapshot');
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
