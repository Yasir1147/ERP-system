<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('default_reminder_days')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->index();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('reminder_days')->nullable();
            $table->boolean('notification_enabled')->default(true)->index();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('notification_email')->nullable();
            $table->string('whatsapp_number', 30)->nullable();
            $table->timestamp('notifications_stopped_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['notification_enabled', 'expiry_date'], 'employee_documents_reminder_index');
        });

        Schema::create('document_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->date('notification_date');
            $table->string('status', 20)->index();
            $table->string('recipient')->nullable();
            $table->integer('days_until_expiry');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['employee_document_id', 'channel', 'notification_date'],
                'document_notification_daily_unique',
            );
        });

        $now = now();
        DB::table('document_categories')->insert([
            ['name' => 'Passport', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Emirates ID', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'IRATA Certificate', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Visa', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Medical Certificate', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Driving Licence', 'default_reminder_days' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_notification_logs');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_categories');
    }
};
