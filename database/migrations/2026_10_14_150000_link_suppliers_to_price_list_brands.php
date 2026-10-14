<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brands explicitly present in supplier price-list importers.
     *
     * Each inner array contains catalogue spelling variants for one brand. The
     * first existing catalogue brand is linked, so this migration never creates
     * duplicate public brands merely because a supplier uses another spelling.
     *
     * @var array<int, array{codes: list<string>, names: list<string>, brands: list<list<string>>}>
     */
    private const PRICE_LIST_BRANDS = [
        [
            'codes' => ['bania'],
            'names' => ['BANIA.by', 'Банька'],
            'brands' => [
                ['Везувий'],
                ['Теплодар'],
                ['TMF', 'Термофор'],
                ['Everest', 'Эверест'],
                ['ЭТНА', 'Этна'],
                ['DoorWood'],
                ['ASTON'],
                ['НМК'],
                ['KARINA'],
                ['Сибирь'],
                ['Север'],
                ['Prometall'],
                ['Рубцовский литейный комплекс ЛДВ'],
                ['Банька'],
            ],
        ],
        [
            'codes' => ['ligmet', 'ligmet-group'],
            'names' => ['Лигмет', 'Лигмет груп', 'Лигмет групп'],
            'brands' => [
                ['Ермак'],
                ['КПД'],
                ['Blist'],
                ['FireWay', 'Fireway'],
                ['Ferrum'],
                ['Ferguss', 'Fergus'],
                ['Invicta'],
                ['Kratki'],
                ['MBS'],
                ['Nordflam'],
                ['Panadero'],
                ['Pegas', 'Пегас'],
            ],
        ],
        [
            'codes' => ['thermostudio', 'termostudia'],
            'names' => ['Термостудия'],
            'brands' => [
                ['Ariston'],
                ['Bosch'],
                ['Buderus'],
                ['Candy'],
                ['Ferroli'],
                ['Fondital'],
                ['Kermi'],
                ['Kospel'],
                ['Kiturami'],
                ['Navien'],
                ['Protherm'],
                ['SAS'],
                ['Superlux'],
                ['TEC Line', 'Tecline'],
                ['Teknix'],
                ['Tesy'],
                ['Auraton'],
                ['Vaillant'],
                ['Viessmann'],
                ['Сигнал'],
            ],
        ],
        [
            'codes' => ['maitek-group', 'maytek', 'maytek-group', 'maitek'],
            'names' => ['Майтек Групп', 'Майтек груп', 'Майтек групп'],
            'brands' => [
                ['СТЭН', 'STEN'],
                ['КАРАКАН', 'Каракан'],
                ['GREOLIT', 'Greolit'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('brand_supplier')) {
            return;
        }

        $pairs = [];

        // The linked catalogue product is the most reliable evidence for every
        // supplier, including suppliers not covered by the four external sheets.
        if (Schema::hasTable('supplier_products') && Schema::hasTable('products')) {
            $this->collectPairs(
                DB::table('supplier_products as sp')
                    ->join('products as p', 'p.id', '=', 'sp.product_id')
                    ->whereNotNull('p.brand_id')
                    ->selectRaw('sp.supplier_id, p.brand_id')
                    ->distinct(),
                $pairs,
            );
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'supplier_id')) {
            $this->collectPairs(
                DB::table('products')
                    ->whereNotNull('supplier_id')
                    ->whereNotNull('brand_id')
                    ->select('supplier_id', 'brand_id')
                    ->distinct(),
                $pairs,
            );
        }

        if (Schema::hasTable('supplier_price_items') && Schema::hasTable('products')) {
            $this->collectPairs(
                DB::table('supplier_price_items as spi')
                    ->join('products as p', 'p.id', '=', 'spi.product_id')
                    ->whereNotNull('p.brand_id')
                    ->selectRaw('spi.supplier_id, p.brand_id')
                    ->distinct(),
                $pairs,
            );
        }

        if (Schema::hasTable('integration_sources')
            && Schema::hasColumn('integration_sources', 'supplier_id')
            && Schema::hasTable('integration_products')
            && Schema::hasTable('products')) {
            $this->collectPairs(
                DB::table('integration_products as ip')
                    ->join('integration_sources as source', 'source.id', '=', 'ip.integration_source_id')
                    ->join('products as p', 'p.id', '=', 'ip.product_id')
                    ->whereNotNull('source.supplier_id')
                    ->whereNotNull('p.brand_id')
                    ->selectRaw('source.supplier_id, p.brand_id')
                    ->distinct(),
                $pairs,
            );
        }

        foreach (self::PRICE_LIST_BRANDS as $priceList) {
            $supplierId = $this->findSupplierId($priceList['codes'], $priceList['names']);

            if (! $supplierId) {
                continue;
            }

            foreach ($priceList['brands'] as $aliases) {
                $brandId = $this->findFirstBrandId($aliases);

                if ($brandId) {
                    $pairs[$brandId.':'.$supplierId] = [
                        'brand_id' => $brandId,
                        'supplier_id' => $supplierId,
                    ];
                }
            }
        }

        $now = now();
        collect(array_values($pairs))->chunk(500)->each(function ($chunk) use ($now): void {
            DB::table('brand_supplier')->insertOrIgnore(
                $chunk->map(fn (array $pair): array => $pair + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });
    }

    public function down(): void
    {
        // These are durable directory facts; rolling back code must not erase them.
    }

    /** @param array<string, array{brand_id: int, supplier_id: int}> $pairs */
    private function collectPairs(Builder $query, array &$pairs): void
    {
        foreach ($query->get() as $row) {
            $supplierId = (int) $row->supplier_id;
            $brandId = (int) $row->brand_id;

            if ($supplierId > 0 && $brandId > 0) {
                $pairs[$brandId.':'.$supplierId] = [
                    'brand_id' => $brandId,
                    'supplier_id' => $supplierId,
                ];
            }
        }
    }

    /** @param list<string> $codes @param list<string> $names */
    private function findSupplierId(array $codes, array $names): ?int
    {
        $supplierId = DB::table('suppliers')->whereIn('code', $codes)->value('id');

        if (! $supplierId) {
            $supplierId = DB::table('suppliers')->whereIn('name', $names)->value('id');
        }

        if (! $supplierId) {
            $supplierId = DB::table('suppliers')
                ->where(function (Builder $query) use ($names): void {
                    foreach ($names as $name) {
                        $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%']);
                    }
                })
                ->value('id');
        }

        return $supplierId ? (int) $supplierId : null;
    }

    /** @param list<string> $aliases */
    private function findFirstBrandId(array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $brandId = DB::table('brands')
                ->where('name', $alias)
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($alias)])
                ->value('id');

            if ($brandId) {
                return (int) $brandId;
            }
        }

        return null;
    }
};
