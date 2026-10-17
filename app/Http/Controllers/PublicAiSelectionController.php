<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Models\PublicAiSelectionRun;
use App\Rules\PhoneNotSpam;
use App\Services\ContactRequestNotifier;
use App\Services\PublicAiProductSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicAiSelectionController extends Controller
{
    private const CATEGORIES = ['gas_boiler', 'boiler', 'heat_pump', 'pump', 'gas_meter', 'general'];

    public function categories(PublicAiProductSelection $selection): JsonResponse
    {
        return response()->json(['categories' => $selection->catalogCategories()]);
    }

    public function start(Request $request, PublicAiProductSelection $selection): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'min:5', 'max:1000'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'catalog_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'page_url' => ['nullable', 'string', 'max:1500'],
        ]);

        $result = $selection->start(
            $data['intent'],
            $data['category'] ?? null,
            isset($data['catalog_category_id']) ? (int) $data['catalog_category_id'] : null
        );
        [$run, $runToken] = $this->recordStart($request, $data, $result);
        $result['run_id'] = $run?->id;
        $result['run_token'] = $runToken;

        return response()->json($result);
    }

    public function recommend(Request $request, PublicAiProductSelection $selection): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'string', 'min:5', 'max:1000'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'catalog_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'run_id' => ['nullable', 'integer'],
            'run_token' => ['nullable', 'string', 'max:100'],
            'page_url' => ['nullable', 'string', 'max:1500'],
            'answers' => ['nullable', 'array', 'max:12'],
            'answers.*' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $selection->recommend(
            $data['intent'],
            $data['category'],
            $data['answers'] ?? [],
            isset($data['catalog_category_id']) ? (int) $data['catalog_category_id'] : null
        );
        $run = $this->recordRecommendation($request, $data, $result);
        $result['run_id'] = $run?->id ?? ($data['run_id'] ?? null);
        $result['run_token'] = $data['run_token'] ?? null;

        return response()->json($result);
    }

    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'run_id' => ['required', 'integer'],
            'run_token' => ['required', 'string', 'max:100'],
            'event' => ['required', Rule::in(['lead_prompted', 'product_opened', 'abandoned'])],
            'product_id' => ['nullable', 'integer'],
            'step' => ['nullable', Rule::in(['questions', 'results', 'lead'])],
        ]);
        $run = $this->ownedRun($request, (int) $data['run_id'], $data['run_token']);
        if (! $run) {
            abort(404);
        }

        if ($data['event'] === 'lead_prompted') {
            $run->update(['lead_prompted_at' => $run->lead_prompted_at ?? now(), 'current_step' => 'lead']);
        } elseif ($data['event'] === 'product_opened') {
            $productId = (int) ($data['product_id'] ?? 0);
            if (in_array($productId, array_map('intval', $run->result_product_ids ?? []), true)) {
                $run->update([
                    'selected_product_id' => $productId,
                    'product_opened_at' => now(),
                    'status' => 'product_opened',
                    'current_step' => 'product',
                ]);
            }
        } else {
            if (in_array($run->status, ['product_opened', 'lead_captured'], true)) {
                return response()->json(['accepted' => true]);
            }
            $updates = [
                'abandoned_at' => $run->abandoned_at ?? now(),
                'current_step' => $data['step'] ?? $run->current_step,
            ];
            if ($run->status === 'started') {
                $updates['status'] = 'abandoned';
            }
            $run->update($updates);
        }

        return response()->json(['accepted' => true]);
    }

    public function contact(Request $request, ContactRequestNotifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'run_id' => ['required', 'integer'],
            'run_token' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', new PhoneNotSpam],
        ]);
        $run = $this->ownedRun($request, (int) $data['run_id'], $data['run_token']);
        if (! $run) {
            abort(404);
        }

        $category = $run->catalogCategory?->name;
        $answers = collect($run->answers ?? [])->map(fn ($value, $key): string => $key.': '.$value)->join('; ');
        $message = implode("\n", array_filter([
            'Запрос: '.$run->intent,
            $category ? 'Категория: '.$category : null,
            $answers !== '' ? 'Ответы: '.$answers : null,
            'Найдено вариантов: '.$run->result_count,
        ]));
        $contactRequest = ContactRequest::query()->create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'message' => $message,
            'status' => 'new',
            'product_name' => $category,
            'product_url' => $run->page_url,
            'source' => 'ai_selection',
        ]);
        $run->update([
            'contact_request_id' => $contactRequest->id,
            'contact_submitted_at' => now(),
            'status' => 'lead_captured',
            'current_step' => 'lead',
        ]);
        $notifier->notify($contactRequest);

        return response()->json(['success' => true, 'message' => 'Спасибо! Менеджер увидит ваш запрос и перезвонит.']);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $result
     */
    private function recordStart(Request $request, array $data, array $result): array
    {
        $runToken = Str::random(48);
        try {
            $run = PublicAiSelectionRun::query()->create([
                'user_id' => $request->user()?->id,
                'session_hash' => hash('sha256', $runToken),
                'intent' => $data['intent'],
                'category' => $result['category'] ?? $data['category'] ?? 'general',
                'catalog_category_id' => $data['catalog_category_id'] ?? null,
                'answers' => $result['known'] ?? [],
                'result_product_ids' => [],
                'result_count' => 0,
                'status' => 'started',
                'current_step' => 'questions',
                'page_url' => $data['page_url'] ?? $request->headers->get('referer'),
            ]);

            return [$run, $runToken];
        } catch (\Throwable $exception) {
            Log::warning('Public AI selection start could not be stored', ['message' => $exception->getMessage()]);

            return [null, null];
        }
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $result
     */
    private function recordRecommendation(Request $request, array $data, array $result): ?PublicAiSelectionRun
    {
        try {
            $run = isset($data['run_id'], $data['run_token'])
                ? $this->ownedRun($request, (int) $data['run_id'], $data['run_token'])
                : null;
            $values = [
                'user_id' => $request->user()?->id,
                'session_hash' => $run?->session_hash ?? hash('sha256', $request->session()->getId()),
                'intent' => $data['intent'],
                'page_url' => $data['page_url'] ?? $run?->page_url ?? $request->headers->get('referer'),
                'category' => $result['category'] ?? $data['category'],
                'catalog_category_id' => $data['catalog_category_id'] ?? null,
                'answers' => $data['answers'] ?? [],
                'result_product_ids' => collect($result['products'] ?? [])->pluck('id')->values()->all(),
                'result_count' => count($result['products'] ?? []),
                'status' => empty($result['products']) ? 'no_results' : 'completed',
                'current_step' => 'results',
                'recommended_at' => now(),
            ];
            if ($run) {
                $run->update($values);

                return $run->fresh();
            }

            return PublicAiSelectionRun::query()->create($values);
        } catch (\Throwable $exception) {
            Log::warning('Public AI selection recommendation could not be stored', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function ownedRun(Request $request, int $runId, string $runToken): ?PublicAiSelectionRun
    {
        return PublicAiSelectionRun::query()
            ->whereKey($runId)
            ->where('session_hash', hash('sha256', $runToken))
            ->first();
    }
}
