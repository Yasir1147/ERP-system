<?php

use App\Mail\DocumentExpiryReminder;
use App\Models\AppSetting;
use App\Models\DocumentCategory;
use App\Models\DocumentNotificationLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

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

test('document register groups multiple documents under one employee', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = expiryTestEmployee();
    $passport = DocumentCategory::query()->where('name', 'Passport')->firstOrFail();
    $emiratesId = DocumentCategory::query()->where('name', 'Emirates ID')->firstOrFail();

    foreach ([
        [$passport->id, 'P-100', today()->addMonths(8)],
        [$emiratesId->id, 'EID-100', today()->addMonths(4)],
    ] as [$categoryId, $number, $expiryDate]) {
        EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $categoryId,
            'document_number' => $number,
            'expiry_date' => $expiryDate,
            'notification_enabled' => true,
            'email_enabled' => false,
            'whatsapp_enabled' => false,
        ]);
    }

    $this->actingAs($admin)
        ->get('/employee-documents')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmployeeDocuments/Index')
            ->has('employeeDocuments', 1)
            ->where('employeeDocuments.0.employeeId', $employee->id)
            ->where('employeeDocuments.0.documentCount', 2)
            ->has('employeeDocuments.0.documents', 2)
            ->where('pagination.total', 1));
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
        'document_default_reminder_days' => '10',
        'document_default_email_enabled' => '1',
        'document_notification_emails' => json_encode(['alerts@example.com', 'manager@example.com']),
        'document_default_whatsapp_enabled' => '0',
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
    Mail::assertSent(DocumentExpiryReminder::class, fn (DocumentExpiryReminder $mail) => $mail
        ->hasTo('alerts@example.com') && $mail->hasTo('manager@example.com'));
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

test('administrator can configure reusable document notification defaults', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->put('/employee-documents/notification-defaults', [
            'reminder_days' => 15,
            'email_enabled' => true,
            'recipient_emails' => "alerts@example.com\nmanager@example.com, alerts@example.com",
            'whatsapp_enabled' => true,
            'whatsapp_number' => '+971501234567',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $defaults = AppSetting::documentNotificationDefaults();

    expect($defaults['reminder_days'])->toBe(15)
        ->and($defaults['email_enabled'])->toBeTrue()
        ->and($defaults['emails'])->toBe(['alerts@example.com', 'manager@example.com'])
        ->and($defaults['whatsapp_enabled'])->toBeTrue()
        ->and($defaults['whatsapp_number'])->toBe('+971501234567');
});
