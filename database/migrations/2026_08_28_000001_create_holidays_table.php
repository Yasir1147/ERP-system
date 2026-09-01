<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            // Null means every employee type observes it.
            $table->string('employee_type')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One holiday per date per audience: the same day cannot be
            // declared twice for the same people.
            $table->unique(['holiday_date', 'employee_type']);
            $table->index('holiday_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
