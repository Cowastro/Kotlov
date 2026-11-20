<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\HaierTechnicalCatalog;
use App\Services\Suppliers\TermTeploFeed;
use App\Services\Suppliers\TermTeploModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncTermTeploCommand extends Command
{
    private const CODE = 'termteplosistem';

    protected $signature = 'supplier:sync-termteplo {--apply : Сохранить проверенные предложения} {--dry-run : Только проверить} {--if-new : Пропускать недавно обработанную версию файлов}';

    protected $description = 'ТермТеплоСистем — актуальный прайс, характеристики и свободные остатки из Google Drive';

    public function handle(TermTeploFeed $feed): int
    {
        $lock = Cache::lock('supplier:sync-termteplo', 1800);
        if (! $lock->get()) {
            $this->error('Уже идёт импорт ТермТеплоСистем');

            return self::FAILURE;
        }
        $paths = [];
        try {
            $folder = Http::timeout(30)->retry(2, 500)->get('https://drive.google.com/embeddedfolderview', ['id' => TermTeploFeed::FOLDER])->throw()->body();
            $sources = $feed->sources($folder);
            $versionKey = 'supplier:termteplo:last-successful-version';
            if ($this->option('if-new') && $this->option('apply') && ! $this->option('dry-run')
                && Cache::get($versionKey) === $sources) {
                $this->info('Ведомость и прайс уже обработаны.');

                return self::SUCCESS;
            }
            foreach ($sources as $kind => $file) {
                $paths[$kind] = tempnam(sys_get_temp_dir(), 'termteplo-');
                $feed->download($file, $kind, $paths[$kind]);
            }
            [$rows, $duplicates, $review] = $feed->read($paths['price'], $paths['stock']);
            $this->info(sprintf('Предложений %d, положительный остаток у %d. Ведомость от %s', count($rows),
                count(array_filter($rows, fn ($row) => $row['quantity'] > 0)), $sources['stock']['date']));
            foreach ([...$review, ...$duplicates] as $item) {
                $this->warn('Требует сверки: '.$item);
            }
            if ($review) {
                $this->error('Структура листов изменилась. Предыдущая загрузка сохранена.');

                return self::FAILURE;
            }
            if ($this->option('apply') && ! $this->option('dry-run')) {
                DB::transaction(fn () => $this->save($rows, $sources, $duplicates));
                Cache::put($versionKey, $sources, now()->addDay());
            }

            return self::SUCCESS;
        } finally {
            foreach (array_filter($paths) as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $lock->release();
        }
    }

    private function save(array $rows, array $sources, array $duplicates): void
    {
        $name = 'ООО «ТермТеплоСистем»';
        $supplier = Supplier::firstOrCreate(['code' => self::CODE], [
            'name' => $name, 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true,
        ]);
        $date = CarbonImmutable::parse($sources['stock']['date'], 'Europe/Minsk')->setTimezone(config('app.timezone'));
        $sync = SupplierSync::firstOrNew(['key' => 'termteplosistem_price']);
        $existing = SupplierProduct::where('supplier_id', $supplier->id)->lockForUpdate()->get()->keyBy('supplier_article');
        if ($existing->contains(fn ($offer) => $offer->last_stock_synced_at?->gt($date))) {
            throw new RuntimeException('В базе уже есть более свежая ведомость');
        }
        $sync->fill([
            'supplier_id' => $supplier->id, 'code' => self::CODE, 'name' => $name, 'title' => $name,
            'description' => 'Прайс в BYN и датированная ведомость свободных остатков.',
            'command' => 'supplier:sync-termteplo', 'source_url' => 'https://drive.google.com/drive/folders/'.TermTeploFeed::FOLDER,
            'is_active' => true, 'last_run_at' => now(), 'last_exit_code' => 0, 'last_status' => 'updated',
        ])->save();
        $touched = [];
        $linked = 0;
        $models = collect($rows)->groupBy(fn ($row) => TermTeploModel::key($row['name'], $row['title']) ?? '')->forget('');
        $cards = Product::with('brand:id,name')->whereHas('brand', fn ($q) => $q->whereIn('name', ['Haier', 'KRATS', 'Arderia']))
            ->orWhere('name', 'like', '%Haier%')->orWhere('name', 'like', '%KRATS%')->orWhere('name', 'like', '%Arderia%')
            ->orWhere('name', 'like', '%Хайер%')->get(['id', 'sku', 'name', 'brand_id'])
            ->groupBy(fn ($card) => TermTeploModel::key($card->name, $card->brand?->name ?? '') ?? '')->forget('');
        $titles = collect($rows)->countBy(fn ($row) => $this->titleKey($row['name']));
        $articles = array_fill_keys(array_column($rows, 'article'), true);
        $byTitle = [];
        $byArticle = $this->reviewedGasBoilerCards($rows);
        foreach (Product::select(['id', 'name', 'specs'])->lazyById(300) as $card) {
            $key = $this->titleKey($card->name);
            if ($key !== '' && ($titles[$key] ?? 0) === 1) {
                $byTitle[$key][] = $card->id;
            }
            foreach ($card->specs ?? [] as $label => $value) {
                if (is_array($value)) {
                    $label = $value['key'] ?? '';
                    $value = $value['value'] ?? null;
                }
                if (in_array(mb_strtolower(trim((string) $label)), ['артикул', 'артикул производителя'], true)
                    && is_scalar($value) && isset($articles[trim((string) $value)])) {
                    $byArticle[trim((string) $value)][] = $card->id;
                }
            }
            // The manufacturer identifies these v3 boilers by article. The feed omits the revision.
            if (preg_match('/^(?:электрический кот[её]л|кот[её]л электрический) arderia e\s*(4|6|9|12|16|20|24),?\s+v3$/iu', trim($card->name), $m)) {
                $article = [4 => '2202208', 6 => '2202209', 9 => '2202210', 12 => '2202211',
                    16 => '2202212', 20 => '2202213', 24 => '2202214'][(int) $m[1]];
                if (isset($rows[$article]) && TermTeploModel::key($rows[$article]['name'], $rows[$article]['title']) === 'arderia:E'.$m[1]) {
                    $byArticle[$article][] = $card->id;
                }
            }
        }
        foreach ($rows as $code => $row) {
            $offer = $existing->pull($code) ?? new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_article' => $code]);
            $product = $offer->product_id ? Product::lockForUpdate()->find($offer->product_id) : null;
            if (! $product && empty($offer->raw['no_auto_link'])) {
                $ids = in_array($row['article'], ['', '—'], true) ? collect() : Product::where('sku', $row['article'])->pluck('id')->merge(
                    SupplierProduct::where('supplier_article', $row['article'])->whereNotNull('product_id')->pluck('product_id')
                )->merge($byArticle[$row['article']] ?? [])->unique();
                if ($ids->isEmpty()) {
                    $ids = collect($byTitle[$this->titleKey($row['name'])] ?? []);
                }
                $product = $ids->count() === 1 ? Product::lockForUpdate()->find($ids->first()) : null;
                $model = TermTeploModel::key($row['name'], $row['title']);
                if ($ids->isEmpty() && $model && ($models[$model] ?? collect())->count() === 1
                    && ($cards[$model] ?? collect())->count() === 1) {
                    $candidate = $cards[$model]->first();
                    if (! preg_match('/^(?:G[A-Z0-9]{7,}|KEB-.*)$/i', (string) $candidate->sku)) {
                        $product = Product::lockForUpdate()->find($candidate->id);
                    }
                }
            }
            $qty = $row['quantity'];
            $offer->fill([
                'supplier_sync_id' => $sync->id, 'product_id' => $product?->id, 'product_sku' => $product?->sku,
                'supplier_name' => mb_substr($row['name'], 0, 255), 'supplier_article_normalized' => mb_strtolower($code),
                'price' => $row['cost'], 'price_byn' => $row['cost'], 'currency' => 'BYN', 'currency_rate' => 1,
                'in_stock' => $qty > 0, 'stock_quantity' => $qty, 'stock_status' => $qty > 0 ? 'in_stock' : 'preorder',
                'warehouse_name' => 'ТермТеплоСистем', 'last_stock_synced_at' => $date, 'last_synced_at' => now(),
                'match_status' => $product ? 'matched' : 'unmatched',
                'raw' => array_merge($offer->raw ?? [], $row, ['sources' => $sources]),
            ])->save();
            if ($product) {
                $linked++;
                $touched[] = $product->id;
                $product->specs = HaierTechnicalCatalog::append($product->specs ?? [], $row['specs']);
                if ($row['retail'] > 0 && $product->operationalIntegrationProducts()->isEmpty()
                    && ! $product->operationalSupplierProducts()->contains(fn ($item) => $item->supplier_id != $supplier->id)) {
                    $product->price = $row['retail'];
                    $product->requires_price_confirmation = false;
                }
                if ($product->isDirty()) {
                    $product->save();
                }
            }
        }
        foreach ($existing as $gone) {
            if (in_array($gone->supplier_article, $duplicates, true)) {
                continue;
            }
            $gone->update(['in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'missing_from_feed',
                'last_stock_synced_at' => $date]);
            if ($gone->product_id) {
                $touched[] = $gone->product_id;
            }
        }
        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $this->info("Связано с карточками KOTLOV: {$linked}");
        $this->line("metric.termteplo.linked={$linked}");
        $this->line('metric.termteplo.unlinked='.(count($rows) - $linked));
    }

    private function reviewedGasBoilerCards(array $rows): array
    {
        // Legacy storefront URLs sometimes encode the wrong power. Verify both catalog identities and titles.
        $models = [
            'PS-011.255' => ['2201356', 'D18'],
            'PS-011.256' => ['2201357', 'D24'],
            'PS-011.776' => ['2201358', 'D28'],
            'PS-011.777' => ['2201359', 'D32'],
            'PS-011.778' => ['2201360', 'D40'],
            'PS-011.780' => ['2201383', 'D24 Atmo'],
            'PS-011.782' => ['2201375', 'SB24'],
            'PS-011.783' => ['2201376', 'SB28'],
            'PS-011.784' => ['2201377', 'SB32'],
            'PS-011.785' => ['2201378', 'SB40'],
        ];
        $matches = [];
        foreach (Product::whereIn('sku', array_keys($models))->where('is_active', true)->where('is_archived', false)->get(['id', 'sku', 'name']) as $card) {
            [$article, $model] = $models[$card->sku];
            $row = $rows[$article] ?? null;
            $title = 'Котел газовый отопительный водогрейный Arderia '.$model;
            if ($row && $row['quantity'] > 0 && $row['retail'] > 0
                && $this->titleKey($row['name']) === $this->titleKey($title)
                && $this->titleKey($card->name) === $this->titleKey($title.', v3')) {
                $matches[$article][] = $card->id;
            }
        }

        return $matches;
    }

    private function titleKey(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim(str_replace('ё', 'е', mb_strtolower($name))));

        return preg_match('/(?:[a-zа-я]+[- ]?\d|\d[- ]?[a-zа-я]+)/u', $name) ? $name : '';
    }
}
