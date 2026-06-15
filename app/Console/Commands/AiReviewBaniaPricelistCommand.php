<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AiReviewBaniaPricelistCommand extends Command
{
    protected $signature = 'supplier:ai-review-bania-pricelist
        {--review-file= : Manual-review CSV path; defaults to latest BANIA price-list manual-review report}
        {--price-file= : BANIA wholesale XLSX/CSV path for --from-equal-prices}
        {--limit=30 : Maximum rows to send to AI}
        {--offset=0 : Skip N rows after filtering}
        {--cost-repair-only : Review only rows whose possible supplier_product still has supplier cost equal to retail and no google price-list link}
        {--from-equal-prices : Build AI-review rows directly from BANIA supplier_products where supplier cost equals retail and no price-list link exists}
        {--candidates-per-product=3 : For --from-equal-prices, send this many top price-list candidates per product}
        {--build-only : Build the review CSV without sending rows to AI}
        {--min-confidence=95 : Confidence threshold for recommended auto-approval}';

    protected $description = 'Use AI to review BANIA price-list manual-review matches and write a recommendation CSV without database writes.';

    public function handle(): int
    {
        $ai = new AiContentEnricher();
        $buildOnly = (bool) $this->option('build-only');
        if (! $buildOnly && ! $ai->isAvailable()) {
            $this->error('No AI provider configured. Set ANTHROPIC_API_KEY or AI_API_KEY + AI_API_URL + AI_MODEL.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $minConfidence = max(1, min(100, (int) $this->option('min-confidence')));

        if ((bool) $this->option('from-equal-prices')) {
            $priceRows = $this->readPriceRows($this->resolvePriceFile((string) ($this->option('price-file') ?? '')));
            $allRows = $this->buildRowsFromEqualPrices($priceRows, max(1, (int) $this->option('candidates-per-product')));
            $this->info(sprintf('Equal-price source: %d rows built.', count($allRows)));
        } else {
            $reviewFile = $this->resolveReviewFile((string) ($this->option('review-file') ?? ''));
            if (! $reviewFile || ! file_exists($reviewFile)) {
                $this->error('Manual-review CSV not found.');
                return self::FAILURE;
            }

            $allRows = $this->readCsv($reviewFile);
            if ((bool) $this->option('cost-repair-only')) {
                $allRows = $this->filterCostRepairRows($allRows);
                $this->info(sprintf('Cost repair filter: %d rows remain.', count($allRows)));
            }
        }

        $rows = array_slice($allRows, $offset, $limit);

        if ($rows === []) {
            $this->info('Manual-review CSV has no rows.');
            return self::SUCCESS;
        }

        $this->info($buildOnly ? 'AI provider: skipped by --build-only' : 'AI provider: ' . $ai->providerName());
        if (! (bool) $this->option('from-equal-prices')) {
            $this->info('Review file: ' . $reviewFile);
        }

        $results = [];
        foreach ($rows as $index => $row) {
            $this->line(sprintf('[%d/%d] price row %s', $index + 1, count($rows), $row['price_row'] ?? ''));

            $decision = $buildOnly
                ? ['decision' => 'not_enough_data', 'confidence' => 0, 'reason' => 'build-only']
                : $this->reviewRow($ai, $row);
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

    private function resolvePriceFile(string $priceFile): string
    {
        $priceFile = trim($priceFile);
        if ($priceFile === '') {
            $default = storage_path('app/supplier-cache/bania-pricelist.xlsx');
            $priceFile = file_exists($default) ? $default : storage_path('app/pricelists/google/bania-dynamic-price.xlsx');
        } elseif (! str_starts_with($priceFile, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:\\\\/i', $priceFile)) {
            $priceFile = base_path($priceFile);
        }

        if (! file_exists($priceFile)) {
            throw new \RuntimeException('Price file not found: ' . $priceFile);
        }

        return $priceFile;
    }

    private function readPriceRows(string $priceFile): array
    {
        $reader = IOFactory::createReaderForFile($priceFile);
        $reader->setReadDataOnly(true);
        $rows = $reader->load($priceFile)->getActiveSheet()->toArray(null, true, true, false);

        $result = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));
            $article = $this->normalizeArticle((string) ($row[2] ?? ''));
            $price = $this->parseMoney((string) ($row[5] ?? ''));
            if ($index < 5 || $name === '' || $price === null || $price <= 0) {
                continue;
            }

            $result[] = [
                'row' => $index + 1,
                'name' => $name,
                'article' => $article,
                'price' => $price,
                'normalized_name' => $this->normalizeName($name),
            ];
        }

        return $result;
    }

    private function buildRowsFromEqualPrices(array $priceRows, int $candidatesPerProduct): array
    {
        $supplierProducts = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('s.code', 'bania')
            ->whereNotNull('sp.price_byn')
            ->whereNotNull('p.price')
            ->whereRaw('ABS(sp.price_byn - p.price) < 0.01')
            ->select([
                'sp.id as supplier_product_id',
                'sp.product_id',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.raw',
                'p.name as product_name',
                'p.price as product_price',
                'b.name as brand',
            ])
            ->orderByDesc('p.id')
            ->get();

        $rows = [];
        foreach ($supplierProducts as $supplierProduct) {
            $raw = json_decode((string) ($supplierProduct->raw ?? ''), true);
            if (is_array($raw) && ! empty($raw['google_price_list'])) {
                continue;
            }

            $candidates = $this->bestPriceRowsForSupplierProduct($supplierProduct, $priceRows, $candidatesPerProduct);
            if ($candidates === []) {
                continue;
            }

            foreach ($candidates as $candidate) {
                $rows[] = [
                    'price_row' => $candidate['row'],
                    'price_title' => $candidate['name'],
                    'price_article' => $candidate['article'],
                    'price_value' => $candidate['price'],
                    'possible_supplier_product_id' => $supplierProduct->supplier_product_id,
                    'possible_product_id' => $supplierProduct->product_id,
                    'possible_supplier_title' => $supplierProduct->supplier_name,
                    'possible_product_title' => $supplierProduct->product_name,
                    'product_brand' => $supplierProduct->brand,
                    'product_retail_price' => $supplierProduct->product_price,
                    'match_type' => 'equal_price_top_price_row',
                    'confidence' => $candidate['score'],
                    'reason' => 'built from supplier_products where supplier cost equals retail and no google price-list link exists',
                ];
            }
        }

        return $rows;
    }

    private function bestPriceRowsForSupplierProduct(object $supplierProduct, array $priceRows, int $limit): array
    {
        $candidateNames = array_filter([
            $this->normalizeName((string) $supplierProduct->supplier_name),
            $this->normalizeName((string) $supplierProduct->product_name),
        ]);
        $brand = $this->normalizeName((string) ($supplierProduct->brand ?? ''));

        $scored = [];
        foreach ($priceRows as $row) {
            if ($this->hasForeignBrandConflict($row['normalized_name'], $brand)) {
                continue;
            }

            $score = 0;
            foreach ($candidateNames as $candidateName) {
                $score = max($score, $this->candidateScore($row['normalized_name'], $candidateName));
            }

            if ($score < 55) {
                continue;
            }

            $scored[] = $row + ['score' => $score];
        }

        usort($scored, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        $seen = [];
        $result = [];
        foreach ($scored as $row) {
            $key = $row['row'] . '|' . $row['article'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $row;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function filterCostRepairRows(array $rows): array
    {
        $ids = collect($rows)
            ->pluck('possible_supplier_product_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $eligible = [];
        DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->whereIn('sp.id', $ids)
            ->whereNotNull('sp.price_byn')
            ->whereNotNull('p.price')
            ->whereRaw('ABS(sp.price_byn - p.price) < 0.01')
            ->get(['sp.id', 'sp.raw'])
            ->each(function ($row) use (&$eligible): void {
                $raw = json_decode((string) ($row->raw ?? ''), true);
                if (is_array($raw) && ! empty($raw['google_price_list'])) {
                    return;
                }

                $eligible[(int) $row->id] = true;
            });

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => isset($eligible[(int) ($row['possible_supplier_product_id'] ?? 0)])
        ));
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

    private function hasForeignBrandConflict(string $priceName, string $productBrand): bool
    {
        if ($productBrand === '') {
            return false;
        }

        $brandAliases = [
            'везувий' => ['везувий'],
            'теплодар' => ['теплодар', 'сахара', 'русь', 'былина', 'сибирь'],
            'tmf' => ['tmf', 'термофор'],
            'термофор' => ['tmf', 'термофор'],
            'эверест' => ['everest', 'эверест'],
            'everest' => ['everest', 'эверест'],
            'этна' => ['etna', 'этна'],
            'etna' => ['etna', 'этна'],
            'aston' => ['aston', 'астон'],
            'harvia' => ['harvia'],
            'harbin' => ['harbin'],
            'doorwood' => ['doorwood'],
            'факел' => ['факел'],
        ];

        $knownBrands = array_values(array_unique(array_merge(...array_values($brandAliases))));
        $allowedAliases = [];
        foreach ($brandAliases as $brand => $aliases) {
            if ($brand === $productBrand || in_array($productBrand, $aliases, true)) {
                $allowedAliases = array_merge($allowedAliases, $aliases);
            }
        }

        if ($allowedAliases === []) {
            return false;
        }

        foreach ($knownBrands as $knownBrand) {
            if (! str_contains($priceName, $knownBrand)) {
                continue;
            }

            foreach ($allowedAliases as $allowedAlias) {
                if (str_contains($priceName, $allowedAlias)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function candidateScore(string $priceName, string $candidateName): int
    {
        $score = $this->similarity($priceName, $candidateName);

        $priceNumbers = $this->modelNumbers($priceName);
        $candidateNumbers = $this->modelNumbers($candidateName);
        if ($priceNumbers !== [] && $candidateNumbers !== [] && array_intersect($priceNumbers, $candidateNumbers) === []) {
            return min($score, 70);
        }

        return $score;
    }

    private function similarity(string $left, string $right): int
    {
        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 100;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return 92;
        }

        similar_text($left, $right, $percent);
        return (int) round($percent);
    }

    private function modelNumbers(string $name): array
    {
        preg_match_all('/\b\d{1,3}(?:[.,]\d+)?\b/u', $name, $matches);
        $numbers = [];
        foreach ($matches[0] ?? [] as $match) {
            $number = (float) str_replace(',', '.', $match);
            if ($number > 0 && $number <= 100) {
                $numbers[] = (string) rtrim(rtrim((string) $number, '0'), '.');
            }
        }

        return array_values(array_unique($numbers));
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(["\xc2\xa0", '–', '—', '‑'], [' ', '-', '-', '-'], $value);
        $value = preg_replace('/\b(печь|для|бани|банная|электрокаменка|каменка|электрическая|дровяная|пб|чугунная)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^0-9a-zа-яё]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeArticle(string $value): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-ZА-ЯЁ]+/u', '', trim($value)) ?? '');
    }

    private function parseMoney(string $value): ?float
    {
        $normalized = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $normalized = preg_replace('/[^0-9,.\-]/u', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
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
