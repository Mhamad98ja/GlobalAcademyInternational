<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class WhatsAppService
{
    public function sendPasswordResetOtp(string $whatsappNumber, string $otp): void
    {
        $accessToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $template = config('services.whatsapp.password_reset_template');

        if (! $accessToken || ! $phoneNumberId || ! $template) {
            throw new RuntimeException('WhatsApp password reset delivery is not configured.');
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(15)
            ->post('https://graph.facebook.com/'.config('services.whatsapp.version').'/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($whatsappNumber, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => config('services.whatsapp.template_language')],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $otp,
                        ]],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('WhatsApp API rejected the password reset message: '.$response->status()));
            throw new RuntimeException('The WhatsApp reset message could not be sent.');
        }
    }
}
