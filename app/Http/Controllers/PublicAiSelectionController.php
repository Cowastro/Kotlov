<?php

namespace App\Http\Controllers;

use App\Models\PublicAiSelectionRun;
use App\Services\PublicAiProductSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PublicAiSelectionController extends Controller
{
    private const CATEGORIES = ['gas_boiler', 'boiler', 'heat_pump', 'pump', 'gas_meter', 'general'];

    public function start(Request $request, PublicAiProductSelection $selection): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'min:5', 'max:1000'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
        ]);

        return response()->json($selection->start($data['intent'], $data['category'] ?? null));
    }

    public function recommend(Request $request, PublicAiProductSelection $selection): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'min:5', 'max:1000'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'answers' => ['nullable', 'array', 'max:12'],
            'answers.*' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $selection->recommend($data['intent'], $data['category'], $data['answers'] ?? []);
        $this->record($request, $data, $result);

        return response()->json($result);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $result
     */
    private function record(Request $request, array $data, array $result): void
    {
        try {
            PublicAiSelectionRun::query()->create([
                'user_id' => $request->user()?->id,
                'session_hash' => hash('sha256', $request->session()->getId()),
                'intent' => $data['intent'],
                'category' => $result['category'] ?? $data['category'],
                'answers' => $data['answers'] ?? [],
                'result_product_ids' => collect($result['products'] ?? [])->pluck('id')->values()->all(),
                'status' => empty($result['products']) ? 'no_results' : 'completed',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Public AI selection analytics could not be stored', ['message' => $exception->getMessage()]);
        }
    }
}
