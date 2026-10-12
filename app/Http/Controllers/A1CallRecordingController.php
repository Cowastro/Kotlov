<?php

namespace App\Http\Controllers;

use App\Models\TelephonyCall;
use App\Models\TelephonyConnection;
use App\Services\Telephony\A1OpenApiClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class A1CallRecordingController extends Controller
{
    public function __invoke(Request $request, TelephonyCall $telephonyCall, A1OpenApiClient $client): Response
    {
        $user = $request->user();

        abort_unless($user?->is_active && $user->isManager(), 403);
        abort_if(blank($telephonyCall->record_name), 404);

        $connection = TelephonyConnection::query()
            ->where('provider', 'a1')
            ->where('is_enabled', true)
            ->firstOrFail();

        $upstream = $client->recording($connection, $telephonyCall->record_name);

        if ($upstream->status() === 404) {
            abort(404, 'Запись разговора больше не хранится в A1.');
        }

        $upstream->throw();

        return response($upstream->body(), 200, [
            'Content-Type' => $upstream->header('Content-Type') ?: 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="call-'.$telephonyCall->id.'.mp3"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
