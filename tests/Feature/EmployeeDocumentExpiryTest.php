<?php

use App\Mail\DocumentExpiryReminder;
use App\Models\AppSetting;
use App\Models\DocumentCategory;
use App\Models\DocumentNotificationLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function expiryTestEmployee(): Employee
{
    return Employee::query()->create([
        'code' => 'DOC-1001',
        'name' => 'Document Test Employee',
        'profession' => 'Technician',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);
}

test('administrator can create a private employee document reminder', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = expiryTestEmployee();
    $category = DocumentCategory::query()->where('name', 'Passport')->firstOrFail();

    $this->actingAs($admin)
        ->post('/employee-documents', [
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'document_number' => 'P-12345',
            'issue_date' => '2026-07-01',
            'expiry_date' => '2026-08-01',
            'reminder_days' => 10,
            'notification_enabled' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'notification_email' => 'alerts@example.com',
            'whatsapp_number' => null,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/employee-documents');

    $this->assertDatabaseHas('employee_documents', [
        'employee_id' => $employee->id,
        'document_number' => 'P-12345',
        'notification_enabled' => true,
    ]);
});

test('due email reminders send once per day and repeat on the next day until stopped', function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 7, 28)->startOfDay());

    foreach ([
        'mail_enabled' => '1',
        'mail_host' => 'smtp.example.com',
        'mail_port' => '587',
        'mail_from_address' => 'system@example.com',
        'mail_from_name' => 'Al Mohafiz',
    ] as $key => $value) {
        AppSetting::setValue($key, $value);
    }

    $employee = expiryTestEmployee();
    $category = DocumentCategory::query()->where('name', 'Emirates ID')->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_category_id' => $category->id,
        'expiry_date' => today()->addDays(5),
        'reminder_days' => 10,
        'notification_enabled' => true,
        'email_enabled' => true,
        'whatsapp_enabled' => false,
        'notification_email' => 'alerts@example.com',
    ]);

    $this->artisan('documents:send-expiry-reminders')->assertSuccessful();
    $this->artisan('documents:send-expiry-reminders')->assertSuccessful();

    Mail::assertSent(DocumentExpiryReminder::class, 1);
    expect(DocumentNotificationLog::query()->where('employee_document_id', $document->id)->count())->toBe(1);

    $this->travel(1)->day();
    $this->artisan('documents:send-expiry-reminders')->assertSuccessful();

    Mail::assertSent(DocumentExpiryReminder::class, 2);
    expect(DocumentNotificationLog::query()->where('employee_document_id', $document->id)->count())->toBe(2);

    $document->update(['notification_enabled' => false, 'notifications_stopped_at' => now()]);
    $this->travel(1)->day();
    $this->artisan('documents:send-expiry-reminders')->assertSuccessful();

    Mail::assertSent(DocumentExpiryReminder::class, 2);
    expect(DocumentNotificationLog::query()->where('employee_document_id', $document->id)->count())->toBe(2);
});

test('document expiry email includes document notes when provided', function () {
    $employee = expiryTestEmployee();
    $category = DocumentCategory::query()->where('name', 'Visa')->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_category_id' => $category->id,
        'expiry_date' => today()->addDays(5),
        'notes' => "Contact the issuing authority.\nRequest renewed document details.",
        'notification_enabled' => true,
        'email_enabled' => true,
        'whatsapp_enabled' => false,
        'notification_email' => 'alerts@example.com',
    ]);

    $email = new DocumentExpiryReminder($document->load(['employee', 'category']), 5);

    expect($email->render())
        ->toContain('Notes')
        ->toContain('Contact the issuing authority.')
        ->toContain('Request renewed document details.');
});

test('administrator can update the automatic document reminder schedule', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->put('/employee-documents/reminder-schedule', [
            'enabled' => true,
            'time' => '09:30',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $schedule = AppSetting::documentReminderSchedule();

    expect($schedule['enabled'])->toBeTrue()
        ->and($schedule['time'])->toBe('09:30')
        ->and($schedule['timezone'])->toBe('Asia/Dubai');
});
