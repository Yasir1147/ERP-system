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

        $query = EmployeeDocument::query()
            ->with([
                'employee:id,code,name,profession,type,status',
                'category:id,name,default_reminder_days,is_active',
                'notificationLogs' => fn ($query) => $query->latest('notification_date')->latest('id')->limit(4),
            ])
            ->when($categoryId, fn ($query) => $query->where('document_category_id', $categoryId))
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->when($notification === 'enabled', fn ($query) => $query->where('notification_enabled', true))
            ->when($notification === 'disabled', fn ($query) => $query->where('notification_enabled', false))
            ->when($status === 'active', fn ($query) => $query->whereDate('expiry_date', '>', today()->addDays(10)))
            ->when($status === 'expiring', fn ($query) => $query
                ->whereDate('expiry_date', '>=', today())
                ->whereDate('expiry_date', '<=', today()->addDays(10)))
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
            })
            ->orderBy('expiry_date')
            ->orderBy('id');

        $documents = $query->paginate($perPage)->withQueryString();
        $today = today();

        $whatsappSettings = AppSetting::whatsappSettings();
        $reminderSchedule = AppSetting::documentReminderSchedule();

        return Inertia::render('EmployeeDocuments/Index', [
            'documents' => $documents->through(fn (EmployeeDocument $document) => $this->documentRow($document))->items(),
            'pagination' => [
                'currentPage' => $documents->currentPage(),
                'lastPage' => $documents->lastPage(),
                'perPage' => $documents->perPage(),
                'total' => $documents->total(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
            ],
            'summary' => [
                'total' => EmployeeDocument::query()->count(),
                'active' => EmployeeDocument::query()->whereDate('expiry_date', '>', $today->copy()->addDays(10))->count(),
                'expiring' => EmployeeDocument::query()->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $today->copy()->addDays(10))->count(),
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
            'defaultEmail' => $request->user()?->email,
        ]);
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
        $filePath = $request->file('document_file')?->store('employee-documents', 'local');
        unset($data['document_file']);

        EmployeeDocument::query()->create([
            ...$data,
            'file_path' => $filePath,
            'notifications_stopped_at' => ($data['notification_enabled'] ?? false) ? null : now(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('employee-documents.index')->with('success', 'Employee document created.');
    }

    public function update(Request $request, EmployeeDocument $employeeDocument): RedirectResponse
    {
        $data = $this->validatedData($request);
        $oldFilePath = $employeeDocument->file_path;
        unset($data['document_file']);

        if ($request->hasFile('document_file')) {
            $data['file_path'] = $request->file('document_file')->store('employee-documents', 'local');
        }

        $data['notifications_stopped_at'] = ($data['notification_enabled'] ?? false) ? null : ($employeeDocument->notifications_stopped_at ?? now());
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
            'reminder_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notification_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'notification_email' => ['nullable', 'required_if:email_enabled,true', 'email', 'max:255'],
            'whatsapp_number' => ['nullable', 'required_if:whatsapp_enabled,true', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ]);
    }

    private function documentRow(EmployeeDocument $document): array
    {
        $daysUntilExpiry = (int) today()->diffInDays($document->expiry_date, false);
        $status = $daysUntilExpiry < 0 ? 'expired' : ($daysUntilExpiry <= 10 ? 'expiring' : 'active');

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
            'effectiveReminderDays' => $document->effectiveReminderDays(),
            'notificationEnabled' => $document->notification_enabled,
            'emailEnabled' => $document->email_enabled,
            'whatsappEnabled' => $document->whatsapp_enabled,
            'notificationEmail' => $document->notification_email,
            'whatsappNumber' => $document->whatsapp_number,
            'lastNotifications' => $document->notificationLogs->map(fn ($log) => [
                'channel' => $log->channel,
                'status' => $log->status,
                'date' => $log->notification_date?->format('d/m/Y'),
                'error' => $log->error_message,
            ])->values(),
        ];
    }
}
