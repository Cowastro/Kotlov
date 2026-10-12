<?php

namespace App\Http\Controllers;

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

        $payload = $request->isMethod('get') ? $request->query() : $request->json()->all();
        $configuredCompanyId = $connection?->company_id;

        if ($configuredCompanyId && isset($payload['companyId']) && (string) $payload['companyId'] !== (string) $configuredCompanyId) {
            return response()->json(['accepted' => false], 403);
        }

        try {
            $call = $processor->process($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['accepted' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['accepted' => true, 'uuid' => $call->external_uuid]);
    }
}
