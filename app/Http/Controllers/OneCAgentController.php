<?php

namespace App\Http\Controllers;

use App\Models\OneCAgentToken;
use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCPriceBatches;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The agent on the 1C server: it asks for approved price batches and reports the 1C document it created. */
class OneCAgentController extends Controller
{
    public function __construct(private readonly OneCPriceBatches $batches) {}

    public function ping(Request $request): JsonResponse
    {
        return $this->authorized($request) ? response()->json(['ok' => true, 'time' => now()->toIso8601String()])
            : $this->denied();
    }

    public function next(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $batch = $this->batches->claimNext();

        return response()->json($batch ? ['batch' => $this->batches->payload($batch)] : ['batch' => null]);
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
