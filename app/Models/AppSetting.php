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

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        if ($key === 'mail_password' && $value !== '') {
            return Crypt::decryptString($value);
        }

        return $value;
    }

    public static function setValue(string $key, mixed $value): void
    {
        if ($key === 'mail_password' && filled($value)) {
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

        $scheme = $settings['encryption'] === 'none' ? null : $settings['encryption'];

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
}
