<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncTeplovSukhovRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-teplov-sukhov-retail
                            {--apply : Apply verified retail prices and save supplier article links}
                            {--report : Save the complete matching report to storage/app/imports}';

    protected $description = 'Sync only retail prices from the approved Teplov i Sukhov price list. Ambiguous rows are never changed.';

    private const PRICE_FILE = 'imports/sbg-teplov-sukhov-2026-08-10.json';

    public function handle(): int
    {
        $data = json_decode((string) file_get_contents(storage_path('app/' . self::PRICE_FILE)), true);

        if (! is_array($data) || ! is_array($data['rows'] ?? null)) {
            $this->error('Не удалось прочитать подготовленный прайс Теплов и Сухов.');
            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', 'teplov')->first();
        $brand = Brand::query()->where('name', 'Теплов и Сухов')->first();

        if (! $supplier || ! $brand) {
            $this->error('Не найден поставщик teplov или бренд «Теплов и Сухов».');
            return self::FAILURE;
        }

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'price']);

        $apply = (bool) $this->option('apply');
        $stats = ['matched' => 0, 'changed' => 0, 'unchanged' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'conflict' => 0, 'price_list_conflict' => 0];
        $details = [];
        $claimedProducts = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->get(['product_id', 'supplier_article'])
            ->groupBy('product_id')
            ->map(fn ($links) => $links->pluck('supplier_article')->unique()->values()->all())
            ->all();

        $sourceRows = $this->uniqueRows($data['rows']);
        $namesByOriginalArticle = collect($sourceRows)
            ->groupBy(fn (array $row) => trim((string) ($row['original_sku'] ?? $row['sku'] ?? '')))
            ->map(fn ($rows) => $rows->pluck('name')->filter()->unique()->values());

        foreach ($sourceRows as $row) {
            if (($row['_price_list_conflict'] ?? false) === true) {
                $stats['price_list_conflict']++;
                $details[] = [
                    $row['sku'],
                    Str::limit((string) $row['name'], 48),
                    'conflicting source rows: ' . implode(' || ', $row['_price_list_variants'] ?? []),
                ];
                continue;
            }

            $article = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $retail = $this->price($row['retail'] ?? null);

            if ($article === '' || $name === '' || $retail === null) {
                $stats['unmatched']++;
                $details[] = [$article ?: '—', Str::limit($name, 48), 'invalid'];
                continue;
            }

            $existing = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('supplier_article', $article)
                ->first();

            // A previous import stored the raw supplier article. Some rows in
            // the approved workbook reuse that raw value, so the extractor now
            // exposes a stable -01/-02 suffix. Move only a proven matching
            // legacy link to its new unique article; the raw value stays in
            // `raw` for auditing and is never used to overwrite another card.
            $legacyArticle = trim((string) ($row['original_sku'] ?? ''));
            if (! $existing && $legacyArticle !== '' && $legacyArticle !== $article) {
                $legacy = SupplierProduct::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('supplier_article', $legacyArticle)
                    ->first();

                if ($legacy?->product_id) {
                    $legacyProduct = $products->firstWhere('id', $legacy->product_id);
                    $legacyMatches = $legacyProduct ? $this->findMatches(collect([$legacyProduct]), $name) : collect();

                    if ($legacyMatches->count() === 1) {
                        if ($apply) {
                            $legacy->supplier_article = $article;
                            $legacy->save();
                        }
                        $existing = $legacy;
                        $claimedProducts[(int) $legacy->product_id] = [$article];
                    } elseif ($apply) {
                        // If a raw article was reused, a previous import may
                        // have linked it to an unrelated card. Keep it only
                        // when that card matches at least one of the rows that
                        // share this raw article; otherwise remove the stale
                        // supplier relation before it can block a new match.
                        $validForAnyRow = $legacyProduct
                            && ($namesByOriginalArticle[$legacyArticle] ?? collect())
                                ->contains(fn (string $sourceName) => $this->findMatches(collect([$legacyProduct]), $sourceName)->count() === 1);

                        if (! $validForAnyRow) {
                            $legacy->delete();
                            $claimedProducts[(int) $legacy->product_id] = array_values(array_diff(
                                $claimedProducts[(int) $legacy->product_id] ?? [],
                                [$legacyArticle]
                            ));
                        }
                    }
                }
            }

            if ($existing?->product_id) {
                $candidate = $products->firstWhere('id', $existing->product_id);
                $matches = $candidate ? $this->findMatches(collect([$candidate]), $name) : collect();
            } else {
                $matches = $this->findMatches($products, $name);
            }

            if ($matches->count() === 0) {
                if ($apply && $existing) {
                    // A stale link must not make a future price overwrite a different item.
                    $existing->delete();
                    $claimedProducts[(int) $existing->product_id] = array_values(array_diff($claimedProducts[(int) $existing->product_id] ?? [], [$article]));
                }
                $stats['unmatched']++;
                $details[] = [$article, Str::limit($name, 48), 'not matched'];
                continue;
            }

            if ($matches->count() > 1) {
                $stats['ambiguous']++;
                $details[] = [$article, Str::limit($name, 48), 'ambiguous: ' . $matches->pluck('id')->implode(',')];
                continue;
            }

            /** @var Product $product */
            $product = $matches->first();

            $otherArticles = array_values(array_diff($claimedProducts[(int) $product->id] ?? [], [$article]));
            if ($otherArticles !== []) {
                $stats['conflict']++;
                $details[] = [$article, Str::limit($name, 48), 'product already linked to: ' . implode(',', $otherArticles)];
                continue;
            }

            if ($existing && $existing->product_id && (int) $existing->product_id !== (int) $product->id) {
                $stats['conflict']++;
                $details[] = [$article, Str::limit($name, 48), 'supplier link conflict'];
                continue;
            }

            $stats['matched']++;
            $changed = abs((float) $product->price - $retail) >= 0.005;
            $stats[$changed ? 'changed' : 'unchanged']++;
            $details[] = [$article, Str::limit($name, 48), sprintf('#%d %s: %.2f → %.2f%s', $product->id, Str::limit($product->name, 36), (float) $product->price, $retail, $changed ? '' : ' (same)')];

            if (! $apply) {
                continue;
            }

            if ($changed) {
                $product->price = $retail;
                $product->save();
            }

            SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'supplier_article' => $article],
                [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_name' => $name,
                    'price' => $retail,
                    'price_byn' => $retail,
                    'currency' => 'BYN',
                    'match_status' => 'matched',
                    'match_confidence' => 'verified_name_dimensions',
                    'raw' => [
                        'price_date' => $data['price_date'] ?? null,
                        'wholesale' => $row['wholesale'] ?? null,
                        'original_supplier_article' => $row['original_sku'] ?? $article,
                    ],
                    'last_synced_at' => now(),
                ]
            );
            $claimedProducts[(int) $product->id] = [$article];
        }

        $this->info(sprintf('Прайс %s: %s. Режим: %s.', $data['price_date'] ?? '—', $data['price_column'] ?? '—', $apply ? 'APPLY' : 'DRY-RUN'));
        $this->table(['Показатель', 'Количество'], [
            ['Строк в прайсе', count($data['rows'])],
            ['Безопасно сопоставлено', $stats['matched']],
            [$apply ? 'Цен обновлено' : 'Цен будет обновлено', $stats['changed']],
            ['Без изменения', $stats['unchanged']],
            ['Неоднозначных — пропущено', $stats['ambiguous']],
            ['Не найдено — пропущено', $stats['unmatched']],
            ['Конфликтов связи — пропущено', $stats['conflict']],
            ['Конфликтов внутри прайса — пропущено', $stats['price_list_conflict']],
        ]);
        $this->table(['Артикул поставщика', 'Прайс', 'Результат'], array_slice($details, 0, 30));

        if ($this->option('report')) {
            $path = storage_path('app/imports/teplov-sukhov-matching-report.json');
            file_put_contents($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'price_date' => $data['price_date'] ?? null,
                'mode' => $apply ? 'apply' : 'dry-run',
                'stats' => $stats,
                'details' => $details,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Полный отчёт: ' . $path);
        }

        return self::SUCCESS;
    }

    private function findMatches($products, string $supplierName)
    {
        $needleWords = $this->words($supplierName);
        $needleNumbers = $this->numbers($supplierName);
        $needleMeasurements = $this->measurements($supplierName);

        return $products->filter(function (Product $product) use ($needleWords, $needleNumbers, $needleMeasurements) {
            $productWords = $this->words($product->name);
            $productNumbers = $this->numbers($product->name);
            $productMeasurements = $this->measurements($product->name);

            $needleTypes = $this->productTypes($needleWords);
            $productTypes = $this->productTypes($productWords);
            // Supplier naming is often shorter: for example "Зонт" in the
            // price list corresponds to a storefront name "Зонт моно". The
            // price-list type must be present in the card; extra specificity
            // on the card is allowed.
            if ($needleTypes !== [] && array_diff($needleTypes, $productTypes) !== []) {
                return false;
            }

            // Material, thickness and every diameter from the supplier name must match exactly.
            if (array_diff($needleNumbers, $productNumbers) !== []) {
                return false;
            }

            // D250 and L250 are different measurements. Keeping their
            // prefixes prevents a D250/L250 source row from matching, for
            // example, a D120/L250 card merely because both contain "250".
            if (array_diff($needleMeasurements, $productMeasurements) !== []) {
                return false;
            }

            $discriminators = ['базальт', 'керамоволокно', 'black', 'медь'];
            $needleDiscriminators = array_values(array_intersect($discriminators, $needleWords));
            $productDiscriminators = array_values(array_intersect($discriminators, $productWords));
            if (array_diff($needleDiscriminators, $productWords) !== []
                || array_diff($productDiscriminators, $needleWords) !== []) {
                return false;
            }

            $hits = count(array_intersect($needleWords, $productWords));
            // Supplier names and historical card names use different terminology
            // (for example, "адаптер котла" vs "адаптер моно"). Exact material,
            // thickness and all diameters are the primary identity; one shared
            // product-type word is enough only after those numbers match.
            // For simple hardware the workbook can contain only a technical
            // type and one diameter (for example, "Фартук D180"). Once the
            // type and every number match, one shared term is enough. A
            // second candidate still becomes ambiguous and is never applied.
            $required = count($needleNumbers) >= 2 || $needleTypes !== []
                ? 1
                : max(2, min(4, count($needleWords) - 1));

            return $hits >= $required;
        })->values();
    }

    private function words(string $value): array
    {
        $value = $this->normalise($value);
        $ignored = ['теплов', 'сухов', 'котла', 'м', 'мм', 'из', 'для', 'и'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', $value) ?: [],
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, $ignored, true) && ! preg_match('/^\d/', $word)
        )));
    }

    private function productTypes(array $words): array
    {
        // A product type is part of the technical identity, not decoration.
        // Keeping the full vocabulary prevents a condensate drain, platform
        // or cone from being matched to another item with the same diameter.
        $types = [
            'адаптер', 'переход', 'дефлектор', 'заглушка', 'труба', 'тройник',
            'ревизия', 'конденсатосборник', 'конденсатоотвод', 'хомут',
            'кронштейн', 'шибер', 'зонт', 'конус', 'врезка', 'опора',
            'разделка', 'проход', 'площадка', 'отвод', 'конвектор', 'фартук',
            'бак', 'регистр', 'теплообменник', 'пароперегреватель', 'лист',
            'комплект', 'моно', 'термо', 'упш',
        ];

        return array_values(array_intersect($types, $words));
    }

    private function numbers(string $value): array
    {
        $value = $this->normalise($value);
        preg_match_all('/(?<!\p{L})\d+(?:\.\d+)?/u', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /** @return array<int, string> */
    private function measurements(string $value): array
    {
        $value = $this->normalise($value);
        preg_match_all('/\b(?:diam|length)\d+(?:\.\d+)?\b/u', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace([',', 'ø'], ['.', 'd'], $value);
        // «Ф» denotes a diameter only as a standalone marker before a number;
        // replacing every Cyrillic «ф» corrupts ordinary words such as
        // «дефлектор» and makes a technical match impossible.
        $value = preg_replace('/\bф\s*(?=\d)/u', 'd ', $value) ?? $value;
        // Correct historic spelling variants before comparing the technical identity.
        // The catalogue contains "конденсатоовод", while the supplier uses
        // the correct spelling "конденсатоотвод".
        $value = preg_replace('/конденсатоовод/u', 'конденсатоотвод', $value) ?? $value;
        // TиС uses short series codes in the price list (ТМ-Р / ТТ-Р),
        // whereas legacy cards spell the same construction as «моно» / «термо».
        // Keep this distinction: a D120 mono pipe must never be matched to a
        // D120/180 insulated pipe merely because their first diameter matches.
        // Older product names sometimes contain visually identical Latin
        // characters (for example "TРT-Р") copied from supplier files.
        // Treat them as the same *series code* only; do not broadly replace
        // Latin letters in the whole name.
        $value = preg_replace('/\b[тt][мm]\s*[-–]?\s*р\b/ui', 'моно', $value) ?? $value;
        $value = preg_replace('/\b[тt][тt]\s*[-–]?\s*р\b/ui', 'термо', $value) ?? $value;
        $value = preg_replace('/\b(?:зм|дм|зрм|трм|пмм|ом|шм)\s*(?:\(\s*м\s*\))?\s*[-–]?\s*р\b/u', 'моно', $value) ?? $value;
        $value = preg_replace('/\b(?:пмт|от|[тt][рp][тt]|кт|шпмт)\s*[-–]?\s*р\b/ui', 'термо', $value) ?? $value;
        // In the TиС price list "(2S)" / "(3S)" is a construction note
        // for elbows. Older catalogue titles do not contain it; the actual
        // technical identity is still protected by angle, steel, thickness
        // and every diameter, so this marker must not become a fake size.
        $value = preg_replace('/\b[23]s\b/ui', ' ', $value) ?? $value;
        $value = preg_replace('/\bd(?=\d)/u', 'd ', $value) ?? $value;
        // Legacy cards write tube length as L250/L500/L1000, while the
        // workbook uses "L 250". Separate the number so length remains a
        // required technical discriminator instead of being silently ignored.
        $value = preg_replace('/\bl(?=\d)/u', 'l ', $value) ?? $value;
        // Preserve the role of repeated values before punctuation is removed:
        // D250 and L250 must not collapse into one undifferentiated "250".
        $value = preg_replace_callback('/\bd\s*(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))*/u', function (array $matches): string {
            preg_match_all('/\d+(?:\.\d+)?/u', $matches[0], $dimensions);

            return ' ' . implode(' ', array_map(fn (string $dimension): string => 'diam' . $dimension, $dimensions[0] ?? [])) . ' ';
        }, $value) ?? $value;
        $value = preg_replace('/\bl\s*(\d+(?:\.\d+)?)/u', ' length$1 ', $value) ?? $value;
        $value = preg_replace('/\b(?:теплов\s+и\s+сухов|тиc|тис)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/адаптер\s*[-–]?\s*переход/u', 'адаптер переход', $value) ?? $value;
        $value = preg_replace('/\b0?\.(\d)\b/u', '0.$1', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}.]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function price(mixed $value): ?float
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function uniqueRows(array $rows): array
    {
        $grouped = collect($rows)->filter(fn ($row) => trim((string) ($row['sku'] ?? '')) !== '')->groupBy('sku');

        return $grouped->map(function ($sameArticle) {
            $signatures = $sameArticle
                ->map(fn ($row) => trim((string) ($row['name'] ?? '')) . '|' . trim((string) ($row['retail'] ?? '')))
                ->unique();

            $variants = $sameArticle
                ->map(fn ($row) => sprintf(
                    '%s: %s = %s',
                    trim((string) ($row['sheet'] ?? '—')),
                    trim((string) ($row['name'] ?? '—')),
                    trim((string) ($row['retail'] ?? '—')),
                ))
                ->unique()
                ->values();

            $row = $sameArticle->first();
            if ($signatures->count() > 1) {
                $row['_price_list_conflict'] = true;
                $row['_price_list_variants'] = $variants->all();
            }

            return $row;
        })->values()->all();
    }
}
