<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;
use App\Services\AiContentEnricher;
use Illuminate\Support\Str;

class IntegrationIssueAiAdvisor
{
    public function __construct(
        private readonly AiContentEnricher $ai,
        private readonly IntegrationIssueAdvisor $rules,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isAvailable();
    }

    /** @return array{title:string,steps:array<int,string>,note:string,source:string,provider:string} */
    public function advise(IntegrationIssue $issue): array
    {
        $fallback = $this->fallback($issue);

        if (! $this->ai->isAvailable()) {
            return $fallback;
        }

        if ($issue->exists) {
            $issue->loadMissing(['source', 'integrationProduct', 'order']);
        }
        $context = json_encode($this->technicalContext($issue), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $rulePlan = json_encode($this->rules->advise($issue), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Ты — помощник оператора интеграций маркетплейса kotlov.by.
Разбери техническую проблему обмена с 1С и предложи безопасный план диагностики на русском языке.

Правила:
- используй только факты из контекста;
- не придумывай статусы, товары, цены и результаты обмена;
- не предлагай удалять данные, обходить защиту или вручную отмечать обмен успешным;
- не меняй товар, заказ или привязку автоматически;
- максимум 4 коротких шага;
- локальный план ниже является безопасной основой: уточни его, но не отменяй защитные ограничения.

Технический контекст (персональные данные клиента не передаются):
{$context}

Безопасный локальный план:
{$rulePlan}

Ответ строго JSON без Markdown:
{"title":"короткое действие","steps":["шаг 1","шаг 2"],"note":"важное ограничение"}
PROMPT;

        $answer = $this->ai->complete($prompt, 650);
        $decoded = $this->decode($answer);

        if (! $decoded) {
            return $fallback;
        }

        $steps = collect($decoded['steps'] ?? [])
            ->filter(fn (mixed $step): bool => is_string($step) && trim($step) !== '')
            ->take(4)
            ->map(fn (string $step): string => Str::limit(trim(strip_tags($step)), 260))
            ->values()
            ->all();
        $title = Str::limit(trim(strip_tags((string) ($decoded['title'] ?? ''))), 120);

        if ($title === '' || $steps === []) {
            return $fallback;
        }

        return [
            'title' => $title,
            'steps' => $steps,
            'note' => Str::limit(
                trim(strip_tags((string) ($decoded['note'] ?? $fallback['note']))) ?: $fallback['note'],
                300,
            ),
            'source' => 'ai',
            'provider' => $this->ai->providerName(),
        ];
    }

    /** @return array<string, mixed> */
    private function technicalContext(IntegrationIssue $issue): array
    {
        return array_filter([
            'issue_type' => $issue->type,
            'severity' => $issue->severity,
            'issue_title' => $issue->title,
            'source' => $issue->source?->name,
            'product' => $issue->integrationProduct ? [
                'external_id' => $issue->integrationProduct->external_id,
                'external_sku' => $issue->integrationProduct->external_sku,
                'name' => $issue->integrationProduct->name,
                'price' => $issue->integrationProduct->price,
                'stock_quantity' => $issue->integrationProduct->stock_quantity,
                'match_status' => $issue->integrationProduct->match_status,
            ] : null,
            'order' => $issue->order ? [
                'number' => $issue->order->number,
                'status' => $issue->order->status,
                'payment_status' => $issue->order->payment_status,
                'onec_status' => $issue->order->onec_status,
                'onec_status_received_at' => $issue->order->onec_status_received_at?->toIso8601String(),
                'onec_exported_at' => $issue->order->onec_exported_at?->toIso8601String(),
            ] : null,
            'detector_context' => collect($issue->context ?? [])
                ->only([
                    'missing_price', 'unmatched', 'missing_category', 'stale_after_minutes',
                    'incoming_status', 'incoming_payment_status', 'unknown_status',
                    'unknown_payment_status', 'external_ids', 'identities', 'flow',
                    'direction', 'operation', 'last_run_status', 'last_success_at',
                    'route_status', 'last_attempted_at', 'delay_minutes', 'exported_at',
                    'response_timeout_minutes',
                ])
                ->all(),
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /** @return array{title:string,steps:array<int,string>,note:string,source:string,provider:string} */
    private function fallback(IntegrationIssue $issue): array
    {
        return [
            ...$this->rules->advise($issue),
            'source' => 'rules',
            'provider' => 'Локальные правила',
        ];
    }

    /** @return array<string, mixed>|null */
    private function decode(?string $answer): ?array
    {
        if (! $answer || ! preg_match('/\{.*\}/s', $answer, $match)) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        return is_array($decoded) ? $decoded : null;
    }
}
