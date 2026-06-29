<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fine_date');
            $table->date('deduction_month')->nullable();
            $table->string('reason');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deduction_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_fines');
    }
};
