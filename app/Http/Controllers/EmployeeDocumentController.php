<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $employeeId = $request->integer('employee_id') ?: null;
        $status = (string) $request->query('status', '');
        $notification = (string) $request->query('notification', '');
        $perPage = in_array($request->integer('per_page', 15), [15, 25, 50], true)
            ? $request->integer('per_page', 15)
            : 15;
        $notificationDefaults = AppSetting::documentNotificationDefaults($request->user()?->email);
        $reminderDays = $notificationDefaults['reminder_days'];

        $documentFilter = function ($query) use ($categoryId, $employeeId, $notification, $status, $reminderDays, $search) {
            $query
                ->when($categoryId, fn ($query) => $query->where('document_category_id', $categoryId))
                ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
                ->when($notification === 'enabled', fn ($query) => $query->where('notification_enabled', true))
                ->when($notification === 'disabled', fn ($query) => $query->where('notification_enabled', false))
                ->when($status === 'active', fn ($query) => $query->whereDate('expiry_date', '>', today()->addDays($reminderDays)))
                ->when($status === 'expiring', fn ($query) => $query
                    ->whereDate('expiry_date', '>=', today())
                    ->whereDate('expiry_date', '<=', today()->addDays($reminderDays)))
                ->when($status === 'expired', fn ($query) => $query->whereDate('expiry_date', '<', today()))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('document_number', 'like', '%'.$search.'%')
                            ->orWhere('notes', 'like', '%'.$search.'%')
                            ->orWhere('notification_email', 'like', '%'.$search.'%')
                            ->orWhere('whatsapp_number', 'like', '%'.$search.'%')
                            ->orWhereHas('employee', fn ($query) => $query
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%'))
                            ->orWhereHas('category', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                    });
                });
        };

        $employeeDocuments = Employee::query()
            ->whereHas('documents', fn ($query) => $documentFilter($query))
            ->with(['documents' => function ($query) {
                $query
                    ->with([
                        'employee:id,code,name,profession,type,status',
                        'category:id,name,default_reminder_days,is_active',
                        'notificationLogs' => fn ($query) => $query->latest('notification_date')->latest('id')->limit(4),
                    ])
                    ->orderBy('expiry_date')
                    ->orderBy('id');
            }])
            ->orderBy('code')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
        $today = today();

        $whatsappSettings = AppSetting::whatsappSettings();
        $reminderSchedule = AppSetting::documentReminderSchedule();

        return Inertia::render('EmployeeDocuments/Index', [
            'employeeDocuments' => $employeeDocuments->through(function (Employee $employee) use ($notificationDefaults) {
                $documents = $employee->documents
                    ->map(fn (EmployeeDocument $document) => $this->documentRow($document, $notificationDefaults))
                    ->values();
                $nearestDocument = $documents->first();

                return [
                    'employeeId' => $employee->id,
                    'employeeCode' => $employee->code,
                    'employeeName' => $employee->name,
                    'employeeProfession' => $employee->profession,
                    'employeeType' => $employee->type,
                    'documentCount' => $documents->count(),
                    'activeCount' => $documents->where('status', 'active')->count(),
                    'expiringCount' => $documents->where('status', 'expiring')->count(),
                    'expiredCount' => $documents->where('status', 'expired')->count(),
                    'notificationsDisabledCount' => $documents->where('notificationEnabled', false)->count(),
                    'nearestDocument' => $nearestDocument,
                    'documents' => $documents,
                ];
            })->items(),
            'pagination' => [
                'currentPage' => $employeeDocuments->currentPage(),
                'lastPage' => $employeeDocuments->lastPage(),
                'perPage' => $employeeDocuments->perPage(),
                'total' => $employeeDocuments->total(),
                'from' => $employeeDocuments->firstItem(),
                'to' => $employeeDocuments->lastItem(),
            ],
            'summary' => [
                'total' => EmployeeDocument::query()->count(),
                'active' => EmployeeDocument::query()->whereDate('expiry_date', '>', $today->copy()->addDays($reminderDays))->count(),
                'expiring' => EmployeeDocument::query()->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $today->copy()->addDays($reminderDays))->count(),
                'expired' => EmployeeDocument::query()->whereDate('expiry_date', '<', $today)->count(),
                'notificationsDisabled' => EmployeeDocument::query()->where('notification_enabled', false)->count(),
            ],
            'categories' => DocumentCategory::query()->withCount('documents')->orderBy('name')->get(),
            'employees' => Employee::query()
                ->orderBy('type')
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'profession', 'type', 'status'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'label' => collect([$employee->code, $employee->name, $employee->profession])->filter()->implode(' - '),
                    'type' => $employee->type,
                    'status' => $employee->status,
                ]),
            'filters' => [
                'search' => $search,
                'categoryId' => $categoryId ? (string) $categoryId : '',
                'employeeId' => $employeeId ? (string) $employeeId : '',
                'status' => $status,
                'notification' => $notification,
                'perPage' => $perPage,
            ],
            'whatsappSettings' => [
                'enabled' => $whatsappSettings['enabled'],
                'graphVersion' => $whatsappSettings['graph_version'],
                'phoneNumberId' => $whatsappSettings['phone_number_id'],
                'templateName' => $whatsappSettings['template_name'],
                'templateLanguage' => $whatsappSettings['template_language'],
                'tokenConfigured' => $whatsappSettings['token_configured'],
            ],
            'reminderSchedule' => [
                'enabled' => $reminderSchedule['enabled'],
                'time' => $reminderSchedule['time'],
                'timezone' => $reminderSchedule['timezone'],
                'lastAutomaticRunAt' => $reminderSchedule['last_automatic_run_at'],
                'lastAutomaticResult' => $reminderSchedule['last_automatic_result'],
            ],
            'notificationDefaults' => [
                'reminderDays' => $notificationDefaults['reminder_days'],
                'emailEnabled' => $notificationDefaults['email_enabled'],
                'emails' => $notificationDefaults['emails'],
                'whatsappEnabled' => $notificationDefaults['whatsapp_enabled'],
                'whatsappNumber' => $notificationDefaults['whatsapp_number'],
            ],
        ]);
    }

    public function updateNotificationDefaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'email_enabled' => ['required', 'boolean'],
            'recipient_emails' => ['nullable', 'string', 'max:5000'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'whatsapp_number' => ['nullable', 'required_if:whatsapp_enabled,true', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['recipient_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values();
        $invalidEmails = $emails->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalidEmails->isNotEmpty()) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Enter valid email addresses separated by commas or new lines.',
            ]);
        }

        if ($data['email_enabled'] && $emails->isEmpty()) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Add at least one recipient email when email reminders are enabled.',
            ]);
        }

        AppSetting::setValue('document_default_reminder_days', $data['reminder_days']);
        AppSetting::setValue('document_default_email_enabled', $data['email_enabled'] ? '1' : '0');
        AppSetting::setValue('document_notification_emails', $emails->toJson());
        AppSetting::setValue('document_default_whatsapp_enabled', $data['whatsapp_enabled'] ? '1' : '0');
        AppSetting::setValue('document_default_whatsapp_number', $data['whatsapp_number'] ?? '');

        return back()->with('success', 'Document notification defaults updated.');
    }

    public function updateReminderSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        AppSetting::setValue('document_reminders_automatic_enabled', $data['enabled'] ? '1' : '0');
        AppSetting::setValue('document_reminders_time', $data['time']);

        return back()->with('success', 'Document reminder schedule updated.');
    }

    public function runReminders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dry_run' => ['required', 'boolean'],
        ]);

        $arguments = $data['dry_run'] ? ['--dry-run' => true] : [];
        $exitCode = Artisan::call('documents:send-expiry-reminders', $arguments);
        $output = trim(Artisan::output());
        $message = str($output ?: 'Document reminder command finished.')->limit(1200)->toString();

        if ($exitCode !== 0) {
            return back()->withErrors(['reminders' => $message]);
        }

        return back()->with('success', $data['dry_run'] ? "Dry run: {$message}" : $message);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $notificationDefaults = AppSetting::documentNotificationDefaults();
        $filePath = $request->file('document_file')?->store('employee-documents', 'local');
        unset($data['document_file']);

        EmployeeDocument::query()->create([
            ...$data,
            'file_path' => $filePath,
            'reminder_days' => null,
            'email_enabled' => $notificationDefaults['email_enabled'],
            'whatsapp_enabled' => $notificationDefaults['whatsapp_enabled'],
            'notification_email' => $notificationDefaults['emails'][0] ?? null,
            'whatsapp_number' => $notificationDefaults['whatsapp_number'] ?: null,
            'notifications_stopped_at' => ($data['notification_enabled'] ?? false) ? null : now(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('employee-documents.index')->with('success', 'Employee document created.');
    }

    public function update(Request $request, EmployeeDocument $employeeDocument): RedirectResponse
    {
        $data = $this->validatedData($request);
        $notificationDefaults = AppSetting::documentNotificationDefaults();
        $oldFilePath = $employeeDocument->file_path;
        unset($data['document_file']);

        if ($request->hasFile('document_file')) {
            $data['file_path'] = $request->file('document_file')->store('employee-documents', 'local');
        }

        $data['notifications_stopped_at'] = ($data['notification_enabled'] ?? false) ? null : ($employeeDocument->notifications_stopped_at ?? now());
        $data['reminder_days'] = null;
        $data['email_enabled'] = $notificationDefaults['email_enabled'];
        $data['whatsapp_enabled'] = $notificationDefaults['whatsapp_enabled'];
        $data['notification_email'] = $notificationDefaults['emails'][0] ?? null;
        $data['whatsapp_number'] = $notificationDefaults['whatsapp_number'] ?: null;
        $data['updated_by'] = $request->user()?->id;
        $employeeDocument->update($data);

        if (isset($data['file_path']) && $oldFilePath) {
            Storage::disk('local')->delete($oldFilePath);
        }

        return to_route('employee-documents.index')->with('success', 'Employee document updated.');
    }

    public function toggleNotification(Request $request, EmployeeDocument $employeeDocument): RedirectResponse
    {
        $data = $request->validate([
            'notification_enabled' => ['required', 'boolean'],
        ]);

        $employeeDocument->update([
            'notification_enabled' => $data['notification_enabled'],
            'notifications_stopped_at' => $data['notification_enabled'] ? null : now(),
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', $data['notification_enabled'] ? 'Document notifications enabled.' : 'Document notifications stopped.');
    }

    public function destroy(EmployeeDocument $employeeDocument): RedirectResponse
    {
        $filePath = $employeeDocument->file_path;
        $employeeDocument->delete();

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        return to_route('employee-documents.index')->with('success', 'Employee document deleted.');
    }

    public function download(EmployeeDocument $employeeDocument): StreamedResponse
    {
        abort_unless($employeeDocument->file_path && Storage::disk('local')->exists($employeeDocument->file_path), 404);

        $extension = pathinfo($employeeDocument->file_path, PATHINFO_EXTENSION);
        $name = collect([
            $employeeDocument->employee?->code,
            $employeeDocument->employee?->name,
            $employeeDocument->category?->name,
        ])->filter()->implode('-');

        return Storage::disk('local')->download(
            $employeeDocument->file_path,
            str($name ?: 'employee-document')->slug().($extension ? '.'.$extension : ''),
        );
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'document_category_id' => ['required', 'integer', Rule::exists('document_categories', 'id')],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notification_enabled' => ['required', 'boolean'],
        ]);
    }

    private function documentRow(EmployeeDocument $document, array $notificationDefaults): array
    {
        $daysUntilExpiry = (int) today()->diffInDays($document->expiry_date, false);
        $status = $daysUntilExpiry < 0 ? 'expired' : ($daysUntilExpiry <= $notificationDefaults['reminder_days'] ? 'expiring' : 'active');

        return [
            'id' => $document->id,
            'employeeId' => $document->employee_id,
            'employeeCode' => $document->employee?->code,
            'employeeName' => $document->employee?->name,
            'employeeProfession' => $document->employee?->profession,
            'employeeType' => $document->employee?->type,
            'categoryId' => $document->document_category_id,
            'categoryName' => $document->category?->name,
            'documentNumber' => $document->document_number,
            'issueDate' => $document->issue_date?->toDateString(),
            'expiryDate' => $document->expiry_date->toDateString(),
            'expiryDateLabel' => $document->expiry_date->format('d/m/Y'),
            'daysUntilExpiry' => $daysUntilExpiry,
            'status' => $status,
            'fileAvailable' => filled($document->file_path),
            'notes' => $document->notes,
            'reminderDays' => $document->reminder_days,
            'effectiveReminderDays' => $notificationDefaults['reminder_days'],
            'notificationEnabled' => $document->notification_enabled,
            'emailEnabled' => $notificationDefaults['email_enabled'],
            'whatsappEnabled' => $notificationDefaults['whatsapp_enabled'],
            'notificationEmail' => implode(', ', $notificationDefaults['emails']),
            'whatsappNumber' => $notificationDefaults['whatsapp_number'],
            'lastNotifications' => $document->notificationLogs->map(fn ($log) => [
                'channel' => $log->channel,
                'status' => $log->status,
                'date' => $log->notification_date?->format('d/m/Y'),
                'error' => $log->error_message,
            ])->values(),
        ];
    }
}
