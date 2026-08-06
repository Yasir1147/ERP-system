<?php

namespace App\Console\Commands;

use App\Mail\DocumentExpiryReminder;
use App\Models\AppSetting;
use App\Models\DocumentNotificationLog;
use App\Models\EmployeeDocument;
use App\Services\MetaWhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDocumentExpiryReminders extends Command
{
    protected $signature = 'documents:send-expiry-reminders
        {--dry-run : Show due notifications without sending them}
        {--scheduled : Respect the configured automatic reminder time and daily run limit}';

    protected $description = 'Send daily email and WhatsApp reminders for due employee documents';

    public function handle(MetaWhatsAppService $whatsApp): int
    {
        $schedule = AppSetting::documentReminderSchedule();
        $notificationDefaults = AppSetting::documentNotificationDefaults();
        $now = now(AppSetting::DOCUMENT_REMINDER_TIMEZONE);

        if ($this->option('scheduled')) {
            if (! $schedule['enabled']) {
                $this->line('Automatic document reminders are disabled.');

                return self::SUCCESS;
            }

            if ($now->format('H:i') < $schedule['time']) {
                $this->line("Document reminders are scheduled for {$schedule['time']} {$schedule['timezone']}.");

                return self::SUCCESS;
            }

            if ($schedule['last_automatic_run_date'] === $now->toDateString()) {
                $this->line('Automatic document reminders have already run today.');

                return self::SUCCESS;
            }

            // Claim today's automatic run before sending so overlapping scheduler calls cannot duplicate it.
            AppSetting::setValue('document_reminders_last_automatic_run_date', $now->toDateString());
            AppSetting::setValue('document_reminders_last_automatic_run_at', $now->toIso8601String());
        }

        $today = $now->copy()->startOfDay();
        $sent = 0;
        $failed = 0;

        EmployeeDocument::query()
            ->with(['employee', 'category'])
            ->where('notification_enabled', true)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(365))
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($today, $whatsApp, $notificationDefaults, &$sent, &$failed) {
                foreach ($documents as $document) {
                    $daysUntilExpiry = (int) $today->diffInDays($document->expiry_date, false);

                    if ($daysUntilExpiry > $notificationDefaults['reminder_days']) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Due: {$document->id} / {$document->employee?->name} / {$document->category?->name} / {$daysUntilExpiry} day(s)");
                        continue;
                    }

                    if ($notificationDefaults['email_enabled'] && count($notificationDefaults['emails']) > 0) {
                        $emailRecipients = $notificationDefaults['emails'];
                        $this->sendChannel(
                            $document,
                            'email',
                            str(implode(', ', $emailRecipients))->limit(255)->toString(),
                            $daysUntilExpiry,
                            function () use ($document, $daysUntilExpiry, $emailRecipients) {
                                if (! AppSetting::configureMailer()) {
                                    throw new \RuntimeException('SMTP mail settings are incomplete or disabled.');
                                }

                                Mail::to($emailRecipients)->send(new DocumentExpiryReminder($document, $daysUntilExpiry));
                            },
                            $sent,
                            $failed,
                        );
                    }

                    if ($notificationDefaults['whatsapp_enabled'] && filled($notificationDefaults['whatsapp_number'])) {
                        $whatsappNumber = $notificationDefaults['whatsapp_number'];
                        $this->sendChannel(
                            $document,
                            'whatsapp',
                            $whatsappNumber,
                            $daysUntilExpiry,
                            fn () => $whatsApp->sendExpiryReminder($document, $daysUntilExpiry, $whatsappNumber),
                            $sent,
                            $failed,
                        );
                    }
                }
            });

        $this->info("Document reminders finished. Sent: {$sent}; Failed: {$failed}.");

        if ($this->option('scheduled')) {
            AppSetting::setValue('document_reminders_last_automatic_result', "Sent: {$sent}; Failed: {$failed}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function sendChannel(
        EmployeeDocument $document,
        string $channel,
        string $recipient,
        int $daysUntilExpiry,
        callable $send,
        int &$sent,
        int &$failed,
    ): void {
        $log = DocumentNotificationLog::query()->firstOrNew([
            'employee_document_id' => $document->id,
            'channel' => $channel,
            'notification_date' => today()->toDateString(),
        ]);

        if ($log->exists && $log->status === DocumentNotificationLog::STATUS_SENT) {
            return;
        }

        try {
            $send();
            $log->fill([
                'status' => DocumentNotificationLog::STATUS_SENT,
                'recipient' => $recipient,
                'days_until_expiry' => $daysUntilExpiry,
                'error_message' => null,
                'sent_at' => now(),
            ])->save();
            $sent++;
        } catch (\Throwable $exception) {
            $log->fill([
                'status' => DocumentNotificationLog::STATUS_FAILED,
                'recipient' => $recipient,
                'days_until_expiry' => $daysUntilExpiry,
                'error_message' => str($exception->getMessage())->limit(2000),
                'sent_at' => null,
            ])->save();
            $failed++;

            Log::warning('Employee document expiry notification failed.', [
                'employee_document_id' => $document->id,
                'channel' => $channel,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
