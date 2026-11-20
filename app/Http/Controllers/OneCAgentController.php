<?php

namespace App\Http\Controllers;

use App\Models\OneCAgentToken;
use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCPriceBatches;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The agent on the 1C server: it asks for approved price batches and reports the 1C document it created. */
class OneCAgentController extends Controller
{
    public function __construct(private readonly OneCPriceBatches $batches) {}

    public function ping(Request $request): JsonResponse
    {
        return $this->authorized($request) ? response()->json(['ok' => true, 'time' => now()->toIso8601String()])
            : $this->denied();
    }

    public function next(Request $request): JsonResponse|Response
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $batch = $this->batches->claimNext();
        $payload = $batch ? $this->batches->payload($batch) : null;

        // PowerShell 2.0 on the 1C server has no JSON parser: «lines» gives one field per line, «|» separated.
        if ($request->query('format') === 'lines') {
            return response($payload ? $this->lines($payload) : "none\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response()->json(['batch' => $payload]);
    }

    public function result(Request $request, int $batch): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $record = OneCPriceBatch::query()->find($batch);
        if (! $record) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        try {
            $record = $this->batches->finish($record, $request->only(['ok', 'document_number', 'document_key', 'error']));
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true, 'status' => $record->status]);
    }

    /** @param array<string, mixed> $payload */
    private function lines(array $payload): string
    {
        $clean = fn ($value): string => str_replace(['|', "\r", "\n"], ' ', (string) $value);
        $lines = ['batch|'.$payload['batch_id'], 'comment|'.$clean($payload['comment']), 'currency|'.$payload['currency_key']];
        foreach ($payload['price_type_keys'] as $key) {
            $lines[] = 'type|'.$key;
        }
        foreach ($payload['rows'] as $row) {
            $lines[] = implode('|', ['row', $row['item_key'], $row['characteristic_key'], $row['price_type_key'],
                number_format((float) $row['price'], 2, '.', ''), $row['manual'] ? '1' : '0', $clean($row['formula'])]);
        }

        return implode("\n", $lines)."\nend\n";
    }

    private function authorized(Request $request): bool
    {
        $token = OneCAgentToken::findActive($request->bearerToken());
        $token?->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $token !== null;
    }

    private function denied(): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }
}
