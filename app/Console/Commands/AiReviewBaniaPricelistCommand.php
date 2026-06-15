<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;

class AiReviewBaniaPricelistCommand extends Command
{
    protected $signature = 'supplier:ai-review-bania-pricelist
        {--review-file= : Manual-review CSV path; defaults to latest BANIA price-list manual-review report}
        {--limit=30 : Maximum rows to send to AI}
        {--min-confidence=95 : Confidence threshold for recommended auto-approval}';

    protected $description = 'Use AI to review BANIA price-list manual-review matches and write a recommendation CSV without database writes.';

    public function handle(): int
    {
        $ai = new AiContentEnricher();
        if (! $ai->isAvailable()) {
            $this->error('No AI provider configured. Set ANTHROPIC_API_KEY or AI_API_KEY + AI_API_URL + AI_MODEL.');
            return self::FAILURE;
        }

        $reviewFile = $this->resolveReviewFile((string) ($this->option('review-file') ?? ''));
        if (! $reviewFile || ! file_exists($reviewFile)) {
            $this->error('Manual-review CSV not found.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $minConfidence = max(1, min(100, (int) $this->option('min-confidence')));
        $rows = array_slice($this->readCsv($reviewFile), 0, $limit);

        if ($rows === []) {
            $this->info('Manual-review CSV has no rows.');
            return self::SUCCESS;
        }

        $this->info('AI provider: ' . $ai->providerName());
        $this->info('Review file: ' . $reviewFile);

        $results = [];
        foreach ($rows as $index => $row) {
            $this->line(sprintf('[%d/%d] price row %s', $index + 1, count($rows), $row['price_row'] ?? ''));

            $decision = $this->reviewRow($ai, $row);
            $confidence = (int) ($decision['confidence'] ?? 0);
            $aiDecision = (string) ($decision['decision'] ?? 'not_enough_data');

            $results[] = $row + [
                'ai_decision' => $aiDecision,
                'ai_confidence' => $confidence,
                'ai_reason' => (string) ($decision['reason'] ?? ''),
                'recommended_action' => $aiDecision === 'approved_match' && $confidence >= $minConfidence
                    ? 'can_apply_after_review'
                    : 'keep_manual_review',
            ];
        }

        $path = storage_path('app/reports/bania/price-list-ai-review-' . now()->format('Ymd-His') . '.csv');
        $this->writeCsv($path, $results);

        $approved = collect($results)->where('recommended_action', 'can_apply_after_review')->count();
        $this->table(['metric', 'count'], [
            ['reviewed', count($results)],
            ['can_apply_after_review', $approved],
            ['keep_manual_review', count($results) - $approved],
        ]);
        $this->info('AI review written: ' . $path);

        return self::SUCCESS;
    }

    private function resolveReviewFile(string $path): ?string
    {
        $path = trim($path);
        if ($path !== '') {
            if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:\\\\/i', $path)) {
                $path = base_path($path);
            }

            return $path;
        }

        $files = glob(storage_path('app/reports/bania/price-list-manual-review-*.csv')) ?: [];
        rsort($files);

        return $files[0] ?? null;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            return [];
        }

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (! is_array($values) || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[(string) $header] = $values[$index] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    private function reviewRow(AiContentEnricher $ai, array $row): array
    {
        $prompt = <<<PROMPT
You are reviewing whether a BANIA.by supplier price-list row should be matched to an existing supplier_product in a catalog.

Return ONLY valid JSON with keys:
decision: one of "approved_match", "different_variant", "not_enough_data"
confidence: integer 0-100
reason: short Russian explanation

Rules:
- Approve only when it is the same product/model, not just the same brand or product family.
- Model numbers, dimensions, suffixes, materials and modifiers matter.
- Treat different sizes, DT-3 vs DT-4, 205 vs 224 vs 270 vs 505, INOX, panorama, with/without glass, with/without tank, left/right as different variants unless the texts clearly say the same thing.
- The supplier article is useful but can be absent or different for variants.
- If unsure, use not_enough_data.

Price-list row:
title: {$row['price_title']}
article: {$row['price_article']}

Possible BANIA supplier product:
supplier_product_id: {$row['possible_supplier_product_id']}
product_id: {$row['possible_product_id']}
supplier title: {$row['possible_supplier_title']}
catalog title: {$row['possible_product_title']}
match type: {$row['match_type']}
current confidence: {$row['confidence']}
current reason: {$row['reason']}
PROMPT;

        $response = $ai->complete($prompt, 500);
        if (! $response) {
            return [
                'decision' => 'not_enough_data',
                'confidence' => 0,
                'reason' => 'AI did not return a response',
            ];
        }

        $json = $this->extractJson($response);
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [
                'decision' => 'not_enough_data',
                'confidence' => 0,
                'reason' => 'AI returned invalid JSON: ' . mb_substr($response, 0, 180),
            ];
        }

        $decision = in_array(($data['decision'] ?? ''), ['approved_match', 'different_variant', 'not_enough_data'], true)
            ? (string) $data['decision']
            : 'not_enough_data';

        return [
            'decision' => $decision,
            'confidence' => max(0, min(100, (int) ($data['confidence'] ?? 0))),
            'reason' => trim((string) ($data['reason'] ?? '')),
        ];
    }

    private function extractJson(string $response): string
    {
        $response = trim($response);
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?\s*/i', '', $response) ?? $response;
            $response = preg_replace('/\s*```$/', '', $response) ?? $response;
        }

        if (preg_match('/\{.*\}/s', $response, $match)) {
            return $match[0];
        }

        return $response;
    }

    private function writeCsv(string $path, array $rows): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot write report: ' . $path);
        }

        if ($rows === []) {
            fputcsv($handle, ['empty']);
            fclose($handle);
            return;
        }

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
