<?php

namespace App\Http\Controllers;

use App\Jobs\SendMissedCallToTelegram;
use App\Models\TelephonyConnection;
use App\Services\Telephony\A1WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class A1TelephonyWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, A1WebhookProcessor $processor): JsonResponse
    {
        $connection = TelephonyConnection::query()->where('provider', 'a1')->first();
        $expectedSecret = (string) ($connection?->webhook_secret ?: config('telephony.webhook_secret'));

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            return response()->json(['accepted' => false], 404);
        }

        if ((int) $request->server('CONTENT_LENGTH', 0) > 65_536) {
            return response()->json(['accepted' => false, 'message' => 'Payload too large'], 413);
        }

        // A1 verifies a newly entered endpoint with a POST that does not contain
        // a call event. Use all() so both JSON and form-encoded notifications
        // are supported, then acknowledge the connectivity probe separately.
        $payload = $request->isMethod('get') ? $request->query() : $request->all();

        if (! isset($payload['notificationType']) && ! isset($payload['uuid'])) {
            return response()->json(['accepted' => true, 'probe' => true]);
        }

        $configuredCompanyId = $connection?->company_id;

        if ($configuredCompanyId && isset($payload['companyId']) && (string) $payload['companyId'] !== (string) $configuredCompanyId) {
            return response()->json(['accepted' => false], 403);
        }

        try {
            $call = $processor->process($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['accepted' => false, 'message' => $exception->getMessage()], 422);
        }

        if ($call->direction === 'inbound'
            && $call->handling_status === 'callback_required'
            && $call->finished_at !== null) {
            // A1 can emit two call legs for one inbound attempt (CANCELLED and
            // NOT_ANSWERED with different UUIDs). Give the final leg time to
            // arrive so the notifier can collapse them into one alert.
            SendMissedCallToTelegram::dispatch($call->id)->delay(now()->addSeconds(30));
        }

        return response()->json(['accepted' => true, 'uuid' => $call->external_uuid]);
    }
}
