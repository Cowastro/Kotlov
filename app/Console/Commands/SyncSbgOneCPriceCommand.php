<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SyncSbgOneCPriceCommand extends Command
{
    protected $signature = 'catalog:sync-sbg-onec-price
        {file : Absolute path to the 1C price export (.xls/.xlsx)}
        {--apply : Write verified retail prices, mappings and 1C staging rows}
        {--report : Save the complete audit report to storage/app/imports}';

    protected $description = 'Synchronize the SBG 1C price export by exact supplier article without fuzzy matching.';

    private const ZERO_UUID = '00000000-0000-0000-0000-000000000000';

    public function handle(): int
    {
        $path = trim((string) $this->argument('file'));
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
        } catch (\Throwable $exception) {
            $this->error('Не удалось прочитать прайс: '.$exception->getMessage());

            return self::FAILURE;
        }

        $supplier = DB::table('suppliers')->where('code', 'teplov')->first(['id', 'code', 'name']);
        $source = DB::table('integration_sources')->where('code', 'onec')->first(['id', 'code', 'name']);
        if (! $supplier || ! $source) {
            $this->error('Не найден поставщик teplov или источник интеграции onec.');

            return self::FAILURE;
        }

        $audit = $this->audit($rows, (int) $supplier->id, (int) $source->id);
        $apply = (bool) $this->option('apply');

        $this->renderSummary($audit, $apply);

        if ($this->option('report')) {
            $reportPath = $this->writeReport($audit, $path, $apply ? 'apply' : 'dry-run');
            $this->info('Полный отчёт: '.$reportPath);
        }

        if (! $apply) {
            $this->warn('DRY RUN: база не изменена. Добавьте --apply после проверки отчёта.');

            return self::SUCCESS;
        }

        if (! Schema::hasColumns('integration_products', [
            'price_currency',
            'price_currency_rate',
            'price_tax_mode',
            'price_vat_rate',
            'price_byn',
        ])) {
            $this->error('Сначала примените миграцию нормализации цен integration_products (2026_10_10_199000).');

            return self::FAILURE;
        }

        $backupPath = $this->writeBackup($audit, (int) $supplier->id, (int) $source->id);
        $result = $this->applyAudit($audit, (int) $supplier->id, (int) $source->id, basename($path));

        $this->newLine();
        $this->info('APPLY завершён. Резервная копия затронутых строк: '.$backupPath);
        $this->table(['Записано', 'Количество'], [
            ['Розничных цен изменено', $result['prices_updated']],
            ['Связей поставщика подтверждено', $result['supplier_links_updated']],
            ['Постоянных сопоставлений сохранено', $result['mappings_saved']],
            ['Строк прайса помещено в staging 1С', $result['integration_rows_staged']],
            ['Строк staging 1С связано с карточками', $result['integration_rows_linked']],
        ]);

        return self::SUCCESS;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function readRows(string $path): Collection
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $sheet = $book->getSheetByName('Лист_1');

        if (! $sheet) {
            throw new RuntimeException('Не найден лист «Лист_1».');
        }

        if (trim((string) $sheet->getCell('B1')->getValue()) !== 'Артикул'
            || ! str_contains((string) $sheet->getCell('C1')->getValue(), 'Номенклатура')
            || trim((string) $sheet->getCell('AM2')->getValue()) !== 'Цена') {
            throw new RuntimeException('Структура колонок не соответствует выгрузке цен 1С.');
        }

        $rows = collect();
        for ($rowNumber = 3; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $article = trim((string) $sheet->getCell("B{$rowNumber}")->getValue());
            $uid = mb_strtolower(trim((string) $sheet->getCell("C{$rowNumber}")->getValue()));
            $characteristicUid = mb_strtolower(trim((string) $sheet->getCell("D{$rowNumber}")->getValue()));
            $name = trim((string) $sheet->getCell("E{$rowNumber}")->getValue());

            if ($uid === '' && $article === '' && $name === '') {
                continue;
            }

            if ($uid === '') {
                throw new RuntimeException("В строке {$rowNumber} отсутствует GUID номенклатуры.");
            }

            $characteristicUid = $characteristicUid !== '' ? $characteristicUid : self::ZERO_UUID;
            $externalId = $characteristicUid !== self::ZERO_UUID
                ? $uid.'#'.$characteristicUid
                : $uid;

            $rows->push([
                'excel_row' => $rowNumber,
                'article' => $article,
                'article_key' => $this->normalizeArticle($article),
                'uid' => $uid,
                'characteristic_uid' => $characteristicUid,
                'external_id' => $externalId,
                'name' => $name,
                'wholesale_without_vat' => $this->money($sheet->getCell("O{$rowNumber}")->getValue()),
                'wholesale_with_vat' => $this->money($sheet->getCell("AG{$rowNumber}")->getValue()),
                'retail_without_vat' => $this->money($sheet->getCell("I{$rowNumber}")->getValue()),
                'retail_with_vat' => $this->money($sheet->getCell("AM{$rowNumber}")->getValue()),
            ]);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function audit(Collection $rows, int $supplierId, int $sourceId): array
    {
        $duplicateArticles = $rows
            ->filter(fn (array $row): bool => $row['article_key'] !== '')
            ->groupBy('article_key')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->keys();
        $duplicateExternalIds = $rows->groupBy('external_id')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->keys();

        $articles = $rows->pluck('article')->filter()->unique()->values();
        $supplierLinks = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereIn('supplier_article', $articles)
            ->get(['id', 'supplier_article', 'product_id', 'product_sku', 'supplier_name', 'price', 'price_byn', 'raw'])
            ->groupBy(fn (object $link): string => $this->normalizeArticle((string) $link->supplier_article));
        $reviewedMappings = DB::table('supplier_product_mappings')
            ->where('supplier_code', 'teplov')
            ->whereIn('supplier_article', $articles)
            ->get(['id', 'supplier_article', 'product_id', 'product_sku', 'is_active'])
            ->keyBy(fn (object $mapping): string => $this->normalizeArticle((string) $mapping->supplier_article));
        $existingIntegration = DB::table('integration_products')
            ->where('integration_source_id', $sourceId)
            ->whereIn('external_id', $rows->pluck('external_id')->unique()->values())
            ->get(['id', 'external_id', 'product_id', 'external_sku', 'match_status'])
            ->keyBy(fn (object $item): string => mb_strtolower((string) $item->external_id));
        $products = DB::table('products')
            ->whereIn('id', $supplierLinks->flatten()->pluck('product_id')->filter()->unique())
            ->get(['id', 'sku', 'name', 'price', 'category_id', 'is_active', 'is_archived'])
            ->keyBy('id');

        $audited = $rows->map(function (array $row) use (
            $duplicateArticles,
            $duplicateExternalIds,
            $supplierLinks,
            $reviewedMappings,
            $existingIntegration,
            $products,
        ): array {
            $issues = [];
            $candidateProductId = null;
            $supplierLinkId = null;

            if ($row['article_key'] === '') {
                $issues[] = 'missing_article';
            } elseif ($duplicateArticles->contains($row['article_key'])) {
                $issues[] = 'duplicate_article_in_price';
            } else {
                $links = $supplierLinks->get($row['article_key'], collect());
                $productIds = $links->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
                if ($productIds->count() === 1) {
                    $candidateProductId = $productIds->first();
                    $supplierLinkId = optional($links->first(fn (object $link): bool => (int) $link->product_id === $candidateProductId))->id;
                } elseif ($productIds->count() > 1) {
                    $issues[] = 'supplier_article_has_multiple_products';
                } else {
                    $issues[] = 'supplier_article_not_linked';
                }
            }

            if ($duplicateExternalIds->contains($row['external_id'])) {
                $issues[] = 'duplicate_onec_identity';
            }

            if ($candidateProductId !== null && ! $products->has($candidateProductId)) {
                $issues[] = 'linked_product_missing';
                $candidateProductId = null;
            }

            $mapping = $reviewedMappings->get($row['article_key']);
            if ($candidateProductId !== null && $mapping?->product_id && (int) $mapping->product_id !== $candidateProductId) {
                $issues[] = 'reviewed_mapping_conflict';
            }

            $integration = $existingIntegration->get($row['external_id']);
            if ($candidateProductId !== null && $integration?->product_id && (int) $integration->product_id !== $candidateProductId) {
                $issues[] = 'onec_mapping_conflict';
            }

            if ($row['retail_with_vat'] === null) {
                $issues[] = 'missing_retail';
            }
            if ($row['retail_with_vat'] !== null
                && $row['wholesale_with_vat'] !== null
                && $row['retail_with_vat'] < $row['wholesale_with_vat']) {
                $issues[] = 'retail_below_wholesale';
            }

            return array_merge($row, [
                'supplier_product_id' => $supplierLinkId,
                'product_id' => $candidateProductId,
                'product' => $candidateProductId !== null ? (array) $products->get($candidateProductId) : null,
                'existing_integration_id' => $integration?->id,
                'issues' => array_values(array_unique($issues)),
            ]);
        });

        $productCollisions = $audited
            ->filter(fn (array $row): bool => $row['product_id'] !== null)
            ->groupBy('product_id')
            ->filter(fn (Collection $group): bool => $group->pluck('article_key')->unique()->count() > 1)
            ->keys()
            ->map(fn ($id): int => (int) $id);

        if ($productCollisions->isNotEmpty()) {
            $audited = $audited->map(function (array $row) use ($productCollisions): array {
                if ($row['product_id'] !== null && $productCollisions->contains((int) $row['product_id'])) {
                    $row['issues'][] = 'multiple_articles_for_product';
                }

                return $row;
            });
        }

        $identityBlocking = [
            'missing_article',
            'duplicate_article_in_price',
            'supplier_article_has_multiple_products',
            'supplier_article_not_linked',
            'linked_product_missing',
            'reviewed_mapping_conflict',
            'onec_mapping_conflict',
            'duplicate_onec_identity',
            'multiple_articles_for_product',
        ];
        $priceBlocking = array_merge($identityBlocking, ['missing_retail', 'retail_below_wholesale']);

        $audited = $audited->map(function (array $row) use ($identityBlocking, $priceBlocking): array {
            $row['safe_link'] = count(array_intersect($identityBlocking, $row['issues'])) === 0;
            $row['safe_price'] = count(array_intersect($priceBlocking, $row['issues'])) === 0;
            $row['price_changed'] = $row['safe_price']
                && abs((float) data_get($row, 'product.price') - (float) $row['retail_with_vat']) >= 0.005;

            return $row;
        });

        return [
            'rows' => $audited,
            'stats' => [
                'source_rows' => $audited->count(),
                'with_article' => $audited->where('article_key', '!=', '')->count(),
                'safe_links' => $audited->where('safe_link', true)->count(),
                'safe_prices' => $audited->where('safe_price', true)->count(),
                'prices_to_change' => $audited->where('price_changed', true)->count(),
                'prices_unchanged' => $audited->where('safe_price', true)->where('price_changed', false)->count(),
                'missing_article' => $audited->filter(fn (array $row): bool => in_array('missing_article', $row['issues'], true))->count(),
                'unlinked_article' => $audited->filter(fn (array $row): bool => in_array('supplier_article_not_linked', $row['issues'], true))->count(),
                'missing_retail' => $audited->filter(fn (array $row): bool => in_array('missing_retail', $row['issues'], true))->count(),
                'retail_below_wholesale' => $audited->filter(fn (array $row): bool => in_array('retail_below_wholesale', $row['issues'], true))->count(),
                'identity_conflicts' => $audited->filter(fn (array $row): bool => count(array_intersect($identityBlocking, $row['issues'])) > 0
                    && ! in_array('missing_article', $row['issues'], true)
                    && ! in_array('supplier_article_not_linked', $row['issues'], true))->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $audit */
    private function renderSummary(array $audit, bool $apply): void
    {
        $stats = $audit['stats'];
        $this->info('Прайс 1С СБГ. Режим: '.($apply ? 'APPLY' : 'DRY-RUN'));
        $this->table(['Проверка', 'Количество'], [
            ['Строк в прайсе', $stats['source_rows']],
            ['Строк с артикулом', $stats['with_article']],
            ['Однозначных связей 1:1', $stats['safe_links']],
            ['Безопасных розничных цен', $stats['safe_prices']],
            [$apply ? 'Цен будет записано' : 'Цен к изменению', $stats['prices_to_change']],
            ['Цен уже совпадает', $stats['prices_unchanged']],
            ['Без артикула', $stats['missing_article']],
            ['Артикул не связан с карточкой', $stats['unlinked_article']],
            ['Без розницы', $stats['missing_retail']],
            ['Розница ниже опта', $stats['retail_below_wholesale']],
            ['Другие конфликты идентичности', $stats['identity_conflicts']],
        ]);

        $problems = $audit['rows']
            ->filter(fn (array $row): bool => $row['issues'] !== [])
            ->take(40)
            ->map(fn (array $row): array => [
                $row['excel_row'],
                $row['article'] ?: '—',
                mb_strimwidth($row['name'], 0, 60, '…'),
                implode(', ', $row['issues']),
            ])
            ->all();

        if ($problems !== []) {
            $this->newLine();
            $this->table(['Строка', 'Артикул', 'Товар', 'Причина пропуска цены'], $problems);
        }
    }

    /** @param array<string, mixed> $audit
     *  @return array<string, int>
     */
    private function applyAudit(array $audit, int $supplierId, int $sourceId, string $sourceFile): array
    {
        $result = [
            'prices_updated' => 0,
            'supplier_links_updated' => 0,
            'mappings_saved' => 0,
            'integration_rows_staged' => 0,
            'integration_rows_linked' => 0,
        ];

        DB::transaction(function () use ($audit, $supplierId, $sourceId, $sourceFile, &$result): void {
            foreach ($audit['rows'] as $row) {
                $now = now();

                if ($row['safe_price'] && $row['price_changed']) {
                    DB::table('products')->where('id', $row['product_id'])->update([
                        'price' => $row['retail_with_vat'],
                        'updated_at' => $now,
                    ]);
                    $result['prices_updated']++;
                }

                if ($row['safe_link']) {
                    $raw = [
                        'source' => '1c_price_export',
                        'source_file' => $sourceFile,
                        'excel_row' => $row['excel_row'],
                        'onec_uid' => $row['uid'],
                        'onec_characteristic_uid' => $row['characteristic_uid'],
                        'wholesale_without_vat' => $row['wholesale_without_vat'],
                        'wholesale_with_vat' => $row['wholesale_with_vat'],
                        'retail_without_vat' => $row['retail_without_vat'],
                        'retail_with_vat' => $row['retail_with_vat'],
                    ];
                    DB::table('supplier_products')->where('id', $row['supplier_product_id'])->update([
                        'product_sku' => data_get($row, 'product.sku'),
                        'supplier_name' => $row['name'],
                        'price' => $row['wholesale_with_vat'],
                        'price_byn' => $row['wholesale_with_vat'],
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        'match_status' => 'matched',
                        'match_confidence' => 'exact_supplier_article',
                        'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE),
                        'last_synced_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $result['supplier_links_updated']++;

                    $mappingKey = ['supplier_code' => 'teplov', 'supplier_article' => $row['article']];
                    $mappingValues = [
                            'product_id' => $row['product_id'],
                            'product_sku' => data_get($row, 'product.sku'),
                            'supplier_name' => $row['name'],
                            'confidence' => 'exact_article',
                            'is_active' => true,
                            'notes' => 'Подтверждено выгрузкой цен 1С; GUID '.$row['external_id'],
                            'updated_at' => $now,
                    ];
                    $mappingQuery = DB::table('supplier_product_mappings')->where($mappingKey);
                    if ($mappingQuery->exists()) {
                        $mappingQuery->update($mappingValues);
                    } else {
                        DB::table('supplier_product_mappings')->insert(array_merge(
                            $mappingKey,
                            $mappingValues,
                            ['created_at' => $now],
                        ));
                    }
                    $result['mappings_saved']++;
                }

                $integrationPayload = [
                    'integration_source_id' => $sourceId,
                    'external_id' => $row['external_id'],
                    'external_sku' => $row['article'] !== '' ? $row['article'] : null,
                    'name' => $row['name'],
                    'price' => $row['wholesale_without_vat'],
                    'price_currency' => 'BYN',
                    'price_currency_rate' => 1,
                    'price_tax_mode' => 'exclusive',
                    'price_vat_rate' => 20,
                    'price_byn' => $row['wholesale_with_vat'],
                    'payload' => json_encode([
                        'source' => '1c_price_export',
                        'source_file' => $sourceFile,
                        'excel_row' => $row['excel_row'],
                        'nomenclature_uid' => $row['uid'],
                        'characteristic_uid' => $row['characteristic_uid'],
                        'retail_without_vat' => $row['retail_without_vat'],
                        'retail_with_vat' => $row['retail_with_vat'],
                    ], JSON_UNESCAPED_UNICODE),
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ];

                if ($row['safe_link']) {
                    $integrationPayload = array_merge($integrationPayload, [
                        'product_id' => $row['product_id'],
                        'target_category_id' => data_get($row, 'product.category_id'),
                        'match_status' => 'matched',
                        'match_method' => 'article_to_supplier_article',
                        'match_confidence' => 1,
                        'candidates' => null,
                        'matched_at' => $now,
                    ]);
                    $result['integration_rows_linked']++;
                } elseif ($row['existing_integration_id'] === null) {
                    $integrationPayload = array_merge($integrationPayload, [
                        'product_id' => null,
                        'match_status' => 'unmatched',
                        'match_method' => null,
                        'match_confidence' => null,
                        'candidates' => null,
                        'matched_at' => null,
                    ]);
                }

                $integrationKey = ['integration_source_id' => $sourceId, 'external_id' => $row['external_id']];
                $integrationQuery = DB::table('integration_products')->where($integrationKey);
                if ($integrationQuery->exists()) {
                    $integrationQuery->update($integrationPayload);
                } else {
                    DB::table('integration_products')->insert(array_merge(
                        $integrationKey,
                        $integrationPayload,
                        ['created_at' => $now],
                    ));
                }
                $result['integration_rows_staged']++;
            }
        });

        return $result;
    }

    /** @param array<string, mixed> $audit */
    private function writeBackup(array $audit, int $supplierId, int $sourceId): string
    {
        $productIds = $audit['rows']->pluck('product_id')->filter()->unique()->values();
        $articles = $audit['rows']->pluck('article')->filter()->unique()->values();
        $externalIds = $audit['rows']->pluck('external_id')->unique()->values();
        $path = storage_path('app/backups/sbg-onec-price-before-'.now()->format('Ymd-His').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'created_at' => now()->toIso8601String(),
            'products' => DB::table('products')->whereIn('id', $productIds)->get(),
            'supplier_products' => DB::table('supplier_products')->where('supplier_id', $supplierId)->whereIn('supplier_article', $articles)->get(),
            'supplier_product_mappings' => DB::table('supplier_product_mappings')->where('supplier_code', 'teplov')->whereIn('supplier_article', $articles)->get(),
            'integration_products' => DB::table('integration_products')->where('integration_source_id', $sourceId)->whereIn('external_id', $externalIds)->get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /** @param array<string, mixed> $audit */
    private function writeReport(array $audit, string $sourcePath, string $mode): string
    {
        $path = storage_path('app/imports/sbg-onec-price-sync-'.now()->format('Ymd-His').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'source_file' => basename($sourcePath),
            'mode' => $mode,
            'stats' => $audit['stats'],
            'rows' => $audit['rows']->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(["\u{00A0}", ' ', ','], ['', '', '.'], trim((string) $value));

        return is_numeric($normalized) && (float) $normalized > 0
            ? round((float) $normalized, 2)
            : null;
    }

    private function normalizeArticle(string $article): string
    {
        return mb_strtoupper(trim($article));
    }
}
