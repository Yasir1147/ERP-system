<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('trn', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number');
            $table->date('bill_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(5);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('unpaid')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'bill_number']);
        });

        Schema::create('purchase_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_bill_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20)->default('material')->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_bill_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('asset_code')->nullable()->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->default(0);
            $table->date('warranty_expiry')->nullable();
            $table->string('status', 30)->default('available')->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_bill_items');
        Schema::dropIfExists('purchase_bills');
        Schema::dropIfExists('suppliers');
    }
};
