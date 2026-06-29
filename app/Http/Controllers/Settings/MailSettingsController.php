<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = AppSetting::mailSettings();

        return Inertia::render('settings/Mail', [
            'settings' => [
                'mail_enabled' => $settings['enabled'],
                'mail_host' => $settings['host'],
                'mail_port' => $settings['port'],
                'mail_username' => $settings['username'],
                'mail_encryption' => $settings['encryption'],
                'mail_from_address' => $settings['from_address'],
                'mail_from_name' => $settings['from_name'],
                'password_configured' => $settings['password_configured'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_enabled' => ['boolean'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        AppSetting::setValue('mail_enabled', (bool) ($data['mail_enabled'] ?? false) ? '1' : '0');
        AppSetting::setValue('mail_host', $data['mail_host'] ?? '');
        AppSetting::setValue('mail_port', $data['mail_port']);
        AppSetting::setValue('mail_username', $data['mail_username'] ?? '');
        AppSetting::setValue('mail_encryption', $data['mail_encryption']);
        AppSetting::setValue('mail_from_address', $data['mail_from_address'] ?? '');
        AppSetting::setValue('mail_from_name', $data['mail_from_name'] ?? '');

        if (filled($data['mail_password'] ?? null)) {
            AppSetting::setValue('mail_password', $data['mail_password']);
        }

        return to_route('mail.edit')->with('success', 'Mail settings saved.');
    }
}
