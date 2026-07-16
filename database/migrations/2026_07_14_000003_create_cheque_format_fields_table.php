<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_format_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_format_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->string('display_name');
            $table->decimal('x_position_mm', 8, 2);
            $table->decimal('y_position_mm', 8, 2);
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('height_mm', 8, 2)->nullable();
            $table->string('font_family', 100)->default('Arial');
            $table->decimal('font_size_pt', 5, 2)->default(10);
            $table->unsignedSmallInteger('font_weight')->default(400);
            $table->boolean('is_italic')->default(false);
            $table->boolean('is_underline')->default(false);
            $table->string('text_align', 10)->default('left');
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['cheque_format_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_format_fields');
    }
};
