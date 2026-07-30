<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsAppSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_enabled' => ['required', 'boolean'],
            'whatsapp_graph_version' => ['nullable', 'regex:/^v[0-9]+\.[0-9]+$/', 'max:20'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:2000'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_template_language' => ['required', 'string', 'max:20'],
        ]);

        AppSetting::setValue('whatsapp_enabled', $data['whatsapp_enabled'] ? '1' : '0');
        AppSetting::setValue('whatsapp_graph_version', $data['whatsapp_graph_version'] ?? '');
        AppSetting::setValue('whatsapp_phone_number_id', $data['whatsapp_phone_number_id'] ?? '');
        AppSetting::setValue('whatsapp_template_name', $data['whatsapp_template_name'] ?? '');
        AppSetting::setValue('whatsapp_template_language', $data['whatsapp_template_language']);

        if (filled($data['whatsapp_access_token'] ?? null)) {
            AppSetting::setValue('whatsapp_access_token', $data['whatsapp_access_token']);
        }

        return back()->with('success', 'WhatsApp Cloud API settings saved.');
    }
}
