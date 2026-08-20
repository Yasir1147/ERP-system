<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public const MAIL_KEYS = [
        'mail_enabled',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public const WHATSAPP_KEYS = [
        'whatsapp_enabled',
        'whatsapp_graph_version',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_template_name',
        'whatsapp_template_language',
    ];

    public const DOCUMENT_REMINDER_TIMEZONE = 'Asia/Dubai';

    private const ENCRYPTED_KEYS = [
        'mail_password',
        'whatsapp_access_token',
    ];

    public const ABSENCE_DEDUCTION_APPLY_FIXED_ONLY = 'fixed_only';

    public static function absenceDeductionSettings(): array
    {
        return [
            'enabled' => static::getValue('absence_deduction_enabled', '0') === '1',
            'apply_to' => static::getValue('absence_deduction_apply_to', self::ABSENCE_DEDUCTION_APPLY_FIXED_ONLY),
        ];
    }

    /**
     * Site overhead carried on top of a worker's wage.
     *
     * A day of labour costs the company more than the day's salary —
     * accommodation, visa, transport, food, insurance. The multiplier turns
     * basic salary into that real burden. It rides on basic pay only:
     * overtime, purchases, and expenses are already at their true cost.
     */
    public static function projectOverheadSettings(): array
    {
        $multiplier = (float) static::getValue('project_overhead_multiplier', '2');

        return [
            'enabled' => static::getValue('project_overhead_enabled', '0') === '1',
            'multiplier' => max(0, min(10, $multiplier)),
        ];
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        if (in_array($key, self::ENCRYPTED_KEYS, true) && $value !== '') {
            return Crypt::decryptString($value);
        }

        return $value;
    }

    public static function setValue(string $key, mixed $value): void
    {
        if (in_array($key, self::ENCRYPTED_KEYS, true) && filled($value)) {
            $value = Crypt::encryptString((string) $value);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );
    }

    public static function mailSettings(): array
    {
        return [
            'enabled' => static::getValue('mail_enabled', '0') === '1',
            'host' => static::getValue('mail_host', ''),
            'port' => (int) static::getValue('mail_port', '587'),
            'username' => static::getValue('mail_username', ''),
            'password' => static::getValue('mail_password', ''),
            'encryption' => static::getValue('mail_encryption', 'tls'),
            'from_address' => static::getValue('mail_from_address', ''),
            'from_name' => static::getValue('mail_from_name', config('app.name')),
            'password_configured' => filled(static::query()->where('key', 'mail_password')->value('value')),
        ];
    }

    public static function configureMailer(): bool
    {
        $settings = static::mailSettings();

        if (! $settings['enabled'] || blank($settings['host']) || blank($settings['from_address'])) {
            return false;
        }

        $scheme = match ($settings['encryption']) {
            'ssl' => 'smtps',
            default => 'smtp',
        };
        

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.username' => $settings['username'] ?: null,
            'mail.mailers.smtp.password' => $settings['password'] ?: null,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'] ?: config('app.name'),
        ]);

        return true;
    }

    public static function whatsappSettings(): array
    {
        return [
            'enabled' => static::getValue('whatsapp_enabled', '0') === '1',
            'graph_version' => static::getValue('whatsapp_graph_version', ''),
            'phone_number_id' => static::getValue('whatsapp_phone_number_id', ''),
            'access_token' => static::getValue('whatsapp_access_token', ''),
            'template_name' => static::getValue('whatsapp_template_name', ''),
            'template_language' => static::getValue('whatsapp_template_language', 'en'),
            'token_configured' => filled(static::query()->where('key', 'whatsapp_access_token')->value('value')),
        ];
    }

    public static function documentReminderSchedule(): array
    {
        return [
            'enabled' => static::getValue('document_reminders_automatic_enabled', '1') === '1',
            'time' => static::getValue('document_reminders_time', '08:00'),
            'timezone' => self::DOCUMENT_REMINDER_TIMEZONE,
            'last_automatic_run_at' => static::getValue('document_reminders_last_automatic_run_at'),
            'last_automatic_run_date' => static::getValue('document_reminders_last_automatic_run_date'),
            'last_automatic_result' => static::getValue('document_reminders_last_automatic_result'),
        ];
    }

    public static function documentNotificationDefaults(?string $fallbackEmail = null): array
    {
        $storedEmails = json_decode(static::getValue('document_notification_emails', '[]') ?: '[]', true);
        $emails = collect(is_array($storedEmails) ? $storedEmails : [])
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty() && filled($fallbackEmail) && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $emails->push($fallbackEmail);
        }

        return [
            'reminder_days' => max(0, min(365, (int) static::getValue('document_default_reminder_days', '10'))),
            'email_enabled' => static::getValue('document_default_email_enabled', '1') === '1',
            'emails' => $emails->all(),
            'whatsapp_enabled' => static::getValue('document_default_whatsapp_enabled', '0') === '1',
            'whatsapp_number' => static::getValue('document_default_whatsapp_number', ''),
        ];
    }
}
