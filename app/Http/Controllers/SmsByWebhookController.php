<?php

namespace App\Http\Controllers;

use App\Models\CrmMailingMessage;
use App\Services\Crm\MailingSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SMS.BY webhooks (API → API вебхуки): delivery status changes and incoming Viber replies. SMS.BY does not sign
 * requests, so the secret is in the URL. A reply «СТОП» puts the phone on the opt-out list.
 */
class SmsByWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, MailingSender $sender): JsonResponse
    {
        $expected = (string) config('services.sms_by.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $event = strtolower((string) $request->input('event_type'));
        if ($event === 'incoming_message' && MailingSender::isStopWord((string) $request->input('message'))) {
            $sender->optOut((string) $request->input('from'), 'viber', 'Ответ «'.mb_substr((string) $request->input('message'), 0, 50).'»');
        } elseif ($event === 'change_status' && is_scalar($request->input('message_id')) && (string) $request->input('message_id') !== '') {
            $channel = strtolower((string) $request->input('channel')) === 'sms' ? 'sms' : 'viber';
            CrmMailingMessage::query()->where('provider_message_id', (string) $request->input('message_id'))->where('channel', $channel)
                ->get()->each(fn (CrmMailingMessage $m) => $sender->applyStatus($m, (string) $request->input('status')));
        }

        return response()->json(['status' => 'OK']);
    }
}
