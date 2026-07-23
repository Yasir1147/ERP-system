<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_format_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->unsignedBigInteger('start_number');
            $table->unsignedBigInteger('end_number');
            $table->unsignedBigInteger('next_number')->nullable();
            $table->date('received_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cheque_format_id', 'reference']);
            $table->index(['cheque_format_id', 'start_number', 'end_number'], 'cheque_books_format_range_index');
        });

        Schema::create('cheque_book_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cheque_number');
            $table->string('status', 20)->default('available')->index();
            $table->timestamps();

            $table->unique(['cheque_book_id', 'cheque_number']);
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('cheque_book_id')->nullable()->after('cheque_format_id')->constrained()->restrictOnDelete();
            $table->foreignId('cheque_book_leaf_id')->nullable()->unique()->after('cheque_book_id')->constrained('cheque_book_leaves')->restrictOnDelete();
        });

        Schema::table('cheque_book_leaves', function (Blueprint $table) {
            $table->foreignId('cheque_id')->nullable()->unique()->after('cheque_number')->constrained('cheques')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cheque_book_leaves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheque_id');
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheque_book_leaf_id');
            $table->dropConstrainedForeignId('cheque_book_id');
        });

        Schema::dropIfExists('cheque_book_leaves');
        Schema::dropIfExists('cheque_books');
    }
};
