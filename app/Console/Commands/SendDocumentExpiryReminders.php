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
    protected $signature = 'documents:send-expiry-reminders {--dry-run : Show due notifications without sending them}';

    protected $description = 'Send daily email and WhatsApp reminders for due employee documents';

    public function handle(MetaWhatsAppService $whatsApp): int
    {
        $today = today();
        $sent = 0;
        $failed = 0;

        EmployeeDocument::query()
            ->with(['employee', 'category'])
            ->where('notification_enabled', true)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(365))
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($today, $whatsApp, &$sent, &$failed) {
                foreach ($documents as $document) {
                    $daysUntilExpiry = (int) $today->diffInDays($document->expiry_date, false);

                    if ($daysUntilExpiry > $document->effectiveReminderDays()) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Due: {$document->id} / {$document->employee?->name} / {$document->category?->name} / {$daysUntilExpiry} day(s)");
                        continue;
                    }

                    if ($document->email_enabled && filled($document->notification_email)) {
                        $this->sendChannel(
                            $document,
                            'email',
                            $document->notification_email,
                            $daysUntilExpiry,
                            function () use ($document, $daysUntilExpiry) {
                                if (! AppSetting::configureMailer()) {
                                    throw new \RuntimeException('SMTP mail settings are incomplete or disabled.');
                                }

                                Mail::to($document->notification_email)->send(new DocumentExpiryReminder($document, $daysUntilExpiry));
                            },
                            $sent,
                            $failed,
                        );
                    }

                    if ($document->whatsapp_enabled && filled($document->whatsapp_number)) {
                        $this->sendChannel(
                            $document,
                            'whatsapp',
                            $document->whatsapp_number,
                            $daysUntilExpiry,
                            fn () => $whatsApp->sendExpiryReminder($document, $daysUntilExpiry),
                            $sent,
                            $failed,
                        );
                    }
                }
            });

        $this->info("Document reminders finished. Sent: {$sent}; Failed: {$failed}.");

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
