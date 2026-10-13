<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSupplierAvailabilityCommand extends Command
{
    private const STOVE_CATEGORY_SLUGS = [
        'pechki',
        'pechi-kaminy',
        'pechi',
        'peci-drovianye-otopitelnye',
        'drovyanye-pechi-dlya-bani',
        'drovianye-peci-bannye',
        'elektrokamenki',
        'elektricheskie-pechi',
    ];

    protected $signature = 'supplier:reconcile-availability
        {--apply : Recalculate catalog availability for every product linked to an active supplier}';

    protected $description = 'Safely reconcile storefront availability with all active supplier stock links.';

    public function handle(SupplierProductAvailabilityService $availability): int
    {
        $legacyProductIds = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('s.is_active', true)
            ->whereNotNull('sp.product_id')
            ->distinct()
            ->pluck('sp.product_id')
            ->map(fn ($id): int => (int) $id);
        $integrationProductIds = DB::table('integration_products as ip')
            ->join('integration_sources as source', 'source.id', '=', 'ip.integration_source_id')
            ->where('source.is_active', true)
            ->where('source.update_stock', true)
            ->where('ip.match_status', 'matched')
            ->whereNotNull('ip.product_id')
            ->distinct()
            ->pluck('ip.product_id')
            ->map(fn ($id): int => (int) $id);
        $productIds = $legacyProductIds
            ->merge($integrationProductIds)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $before = $this->inconsistencyCounts();
        $this->table(['Проверка', 'Количество'], $this->rows($before));
        $this->writeMetrics('before', $before);

        if (! (bool) $this->option('apply')) {
            $this->warn('Предпросмотр: данные не изменены. Для исправления добавьте --apply.');
            return self::SUCCESS;
        }

        foreach (array_chunk($productIds, 500) as $chunk) {
            $availability->refreshMany($chunk);
        }

        // A storefront label of "Уточняйте наличие" must never be paired with
        // the boolean in-stock flag, even for manually managed products that do
        // not currently have an active supplier link.
        DB::table('products')
            ->where('availability_status', Product::AVAILABILITY_CHECK)
            ->where('in_stock', true)
            ->update([
                'in_stock' => false,
                'stock_qty' => null,
                'updated_at' => now(),
            ]);

        // A stove without any active supplier link must never expose a stale
        // retail price or an optimistic stock flag on the storefront.
        $guardedStoves = $this->unlinkedStovesWithPublicOfferQuery()->update([
            'price' => 0,
            'price_old' => null,
            'in_stock' => false,
            'stock_qty' => null,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'updated_at' => now(),
        ]);

        $after = $this->inconsistencyCounts();
        $this->newLine();
        $this->info('Пересчитано связанных товаров: ' . count($productIds));
        $this->info('Печей без поставщика переведено в «цену и наличие уточняйте»: '.$guardedStoves);
        $this->table(['Проверка после исправления', 'Количество'], $this->rows($after));
        $this->writeMetrics('after', $after);

        return array_sum($after) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{false_positive:int,false_negative:int,uncertain_in_stock:int,unlinked_stoves_with_public_offer:int} */
    private function inconsistencyCounts(): array
    {
        $hasActiveLink = fn ($query) => $query
            ->selectRaw('1')
            ->from('supplier_products as linked_sp')
            ->join('suppliers as linked_s', 'linked_s.id', '=', 'linked_sp.supplier_id')
            ->whereColumn('linked_sp.product_id', 'p.id')
            ->where('linked_s.is_active', true);

        $hasConfirmedStock = fn ($query) => $query
            ->selectRaw('1')
            ->from('supplier_products as stock_sp')
            ->join('suppliers as stock_s', 'stock_s.id', '=', 'stock_sp.supplier_id')
            ->whereColumn('stock_sp.product_id', 'p.id')
            ->where('stock_s.is_active', true)
            ->where('stock_sp.in_stock', true);
        $hasActiveIntegrationLink = fn ($query) => $query
            ->selectRaw('1')
            ->from('integration_products as linked_ip')
            ->join('integration_sources as linked_source', 'linked_source.id', '=', 'linked_ip.integration_source_id')
            ->whereColumn('linked_ip.product_id', 'p.id')
            ->where('linked_source.is_active', true)
            ->where('linked_source.update_stock', true)
            ->where('linked_ip.match_status', 'matched');
        $hasConfirmedIntegrationStock = fn ($query) => $query
            ->selectRaw('1')
            ->from('integration_products as stock_ip')
            ->join('integration_sources as stock_source', 'stock_source.id', '=', 'stock_ip.integration_source_id')
            ->whereColumn('stock_ip.product_id', 'p.id')
            ->where('stock_source.is_active', true)
            ->where('stock_source.update_stock', true)
            ->where('stock_ip.match_status', 'matched')
            ->where('stock_ip.stock_quantity', '>', 0);

        return [
            'false_positive' => DB::table('products as p')
                ->where('p.in_stock', true)
                ->where('p.is_archived', false)
                ->where(fn ($query) => $query
                    ->whereExists($hasActiveLink)
                    ->orWhereExists($hasActiveIntegrationLink))
                ->whereNotExists($hasConfirmedStock)
                ->whereNotExists($hasConfirmedIntegrationStock)
                ->count(),
            'false_negative' => DB::table('products as p')
                ->where('p.in_stock', false)
                ->where('p.is_archived', false)
                ->where(fn ($query) => $query
                    ->whereExists($hasConfirmedStock)
                    ->orWhereExists($hasConfirmedIntegrationStock))
                ->count(),
            'uncertain_in_stock' => DB::table('products as p')
                ->where('p.availability_status', Product::AVAILABILITY_CHECK)
                ->where('p.in_stock', true)
                ->where('p.is_archived', false)
                ->count(),
            'unlinked_stoves_with_public_offer' => $this->unlinkedStovesWithPublicOfferQuery()->count(),
        ];
    }

    /** @param array{false_positive:int,false_negative:int,uncertain_in_stock:int,unlinked_stoves_with_public_offer:int} $counts */
    private function rows(array $counts): array
    {
        return [
            ['Нет подтверждённого остатка, но на сайте «В наличии»', $counts['false_positive']],
            ['Остаток подтверждён, но на сайте не «В наличии»', $counts['false_negative']],
            ['«Уточняйте наличие» одновременно с флагом in_stock', $counts['uncertain_in_stock']],
            ['Печь без активного поставщика показывает цену или наличие', $counts['unlinked_stoves_with_public_offer']],
        ];
    }

    /** @param array{false_positive:int,false_negative:int,uncertain_in_stock:int,unlinked_stoves_with_public_offer:int} $counts */
    private function writeMetrics(string $stage, array $counts): void
    {
        foreach ($counts as $key => $value) {
            $this->line("metric.availability.{$stage}.{$key}={$value}");
        }
    }

    private function unlinkedStovesWithPublicOfferQuery()
    {
        $categoryIds = $this->stoveCategoryIds();

        $hasActiveLegacyLink = fn ($query) => $query
            ->selectRaw('1')
            ->from('supplier_products as guard_sp')
            ->join('suppliers as guard_s', 'guard_s.id', '=', 'guard_sp.supplier_id')
            ->whereColumn('guard_sp.product_id', 'p.id')
            ->where('guard_s.is_active', true);
        $hasActiveIntegrationLink = fn ($query) => $query
            ->selectRaw('1')
            ->from('integration_products as guard_ip')
            ->join('integration_sources as guard_source', 'guard_source.id', '=', 'guard_ip.integration_source_id')
            ->whereColumn('guard_ip.product_id', 'p.id')
            ->where('guard_source.is_active', true)
            ->where('guard_ip.match_status', 'matched');

        return DB::table('products as p')
            ->whereIn('p.category_id', $categoryIds)
            ->where('p.is_archived', false)
            ->whereNotExists($hasActiveLegacyLink)
            ->whereNotExists($hasActiveIntegrationLink)
            ->where(fn ($query) => $query
                ->where('p.price', '>', 0)
                ->orWhere('p.in_stock', true)
                ->orWhereNotNull('p.stock_qty')
                ->orWhere('p.availability_status', '!=', Product::AVAILABILITY_CHECK)
                ->orWhereNull('p.availability_status'));
    }

    /** @return array<int, int> */
    private function stoveCategoryIds(): array
    {
        $categories = DB::table('categories')->get(['id', 'parent_id', 'slug']);
        $ids = $categories
            ->whereIn('slug', self::STOVE_CATEGORY_SLUGS)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        do {
            $before = count($ids);
            foreach ($categories as $category) {
                if (in_array((int) $category->parent_id, $ids, true)) {
                    $ids[] = (int) $category->id;
                }
            }
            $ids = array_values(array_unique($ids));
        } while (count($ids) > $before);

        return $ids;
    }
}
