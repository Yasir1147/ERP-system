<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppService
{
    public function sendExpiryReminder(EmployeeDocument $document, int $daysUntilExpiry, ?string $recipient = null): string
    {
        $settings = AppSetting::whatsappSettings();

        if (
            ! $settings['enabled']
            || blank($settings['graph_version'])
            || blank($settings['phone_number_id'])
            || blank($settings['access_token'])
            || blank($settings['template_name'])
        ) {
            throw new RuntimeException('WhatsApp Cloud API settings are incomplete or disabled.');
        }

        $response = Http::withToken($settings['access_token'])
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.facebook.com/{$settings['graph_version']}/{$settings['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient ?: $document->whatsapp_number,
                'type' => 'template',
                'template' => [
                    'name' => $settings['template_name'],
                    'language' => [
                        'code' => $settings['template_language'],
                    ],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => trim(($document->employee?->code ? $document->employee->code.' - ' : '').($document->employee?->name ?? 'Unknown Employee'))],
                            ['type' => 'text', 'text' => $document->category?->name ?? 'Document'],
                            ['type' => 'text', 'text' => $document->expiry_date->format('d/m/Y')],
                            ['type' => 'text', 'text' => $daysUntilExpiry < 0 ? 'Expired '.abs($daysUntilExpiry).' day(s) ago' : $daysUntilExpiry.' day(s) remaining'],
                        ],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Meta WhatsApp API error: '.$response->status().' '.$response->body());
        }

        return (string) data_get($response->json(), 'messages.0.id', 'sent');
    }
}
