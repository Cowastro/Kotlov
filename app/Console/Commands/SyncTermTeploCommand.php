<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\HaierTechnicalCatalog;
use App\Services\Suppliers\TermTeploFeed;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncTermTeploCommand extends Command
{
    private const CODE = 'termteplosistem';

    protected $signature = 'supplier:sync-termteplo {--apply : Сохранить проверенные предложения} {--dry-run : Только проверить}';

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
        $models = collect($rows)->filter(fn ($row) => str_contains(mb_strtolower($row['name'].' '.$row['title']), 'haier'))
            ->groupBy(fn ($row) => HaierTechnicalCatalog::model($row['name']) ?? '')->forget('');
        $cards = Product::where('name', 'like', '%Haier%')->orWhereHas('brand', fn ($q) => $q->where('name', 'Haier'))
            ->get(['id', 'sku', 'name'])->groupBy(fn ($card) => HaierTechnicalCatalog::model($card->name) ?? '')->forget('');
        foreach ($rows as $code => $row) {
            $offer = $existing->pull($code) ?? new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_article' => $code]);
            $product = $offer->product_id ? Product::lockForUpdate()->find($offer->product_id) : null;
            if (! $product && empty($offer->raw['no_auto_link']) && $row['article'] !== '' && $row['article'] !== '—') {
                $ids = Product::where('sku', $row['article'])->pluck('id')->merge(
                    SupplierProduct::where('supplier_article', $row['article'])->whereNotNull('product_id')->pluck('product_id')
                )->unique();
                $product = $ids->count() === 1 ? Product::lockForUpdate()->find($ids->first()) : null;
                $model = HaierTechnicalCatalog::model($row['name']);
                if ($ids->isEmpty() && $model && ($models[$model] ?? collect())->count() === 1
                    && str_contains(mb_strtolower($row['name'].' '.$row['title']), 'haier')
                    && ($cards[$model] ?? collect())->count() === 1) {
                    $candidate = $cards[$model]->first();
                    if (! preg_match('/^G[A-Z0-9]{7,}(RU)?$/i', (string) $candidate->sku)) {
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
                if ($row['retail'] !== null && $product->operationalIntegrationProducts()->isEmpty()
                    && ! $product->operationalSupplierProducts()->contains(fn ($item) => $item->supplier_id != $supplier->id)) {
                    $product->price = $row['retail'];
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
    }
}
