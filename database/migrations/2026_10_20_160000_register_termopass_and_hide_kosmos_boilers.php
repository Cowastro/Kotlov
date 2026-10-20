<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'termopass';

    private const SOURCE_URL = 'https://termopass.by/katalog/tverdotoplivnye-kotly-kosmos/';

    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || ! Schema::hasTable('products')) {
            return;
        }

        $now = now();
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        $supplier ??= DB::table('suppliers')
            ->whereRaw('LOWER(name) LIKE ?', ['%термопасс%'])
            ->first();

        $channels = collect(json_decode((string) ($supplier->communication_channels ?? '[]'), true) ?: [])
            ->filter(fn (mixed $channel): bool => is_array($channel))
            ->reject(function (array $channel): bool {
                $value = mb_strtolower(trim((string) ($channel['value'] ?? '')));

                return in_array($value, ['+375 (44) 554-07-92', '+375445540792', 'info@termopass.by'], true)
                    || preg_replace('/\D/', '', $value) === '375445540792';
            })
            ->values()
            ->all();

        $channels[] = [
            'type' => 'phone',
            'label' => 'Отдел продаж',
            'value' => '+375 (44) 554-07-92',
        ];
        $channels[] = [
            'type' => 'email',
            'label' => 'Заказы',
            'value' => 'info@termopass.by',
        ];

        $supplierValues = [
            'code' => self::SUPPLIER_CODE,
            'name' => 'Термопасс',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => '+375 (44) 554-07-92 · info@termopass.by',
            'notes' => 'Котлы «Космос». Срок поставки 10–14 дней. Модель 12 кВт снята с производства, линейка начинается с 15 кВт.',
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('suppliers', 'communication_channels')) {
            $supplierValues['communication_channels'] = json_encode($channels, JSON_UNESCAPED_UNICODE);
        }

        if ($supplier) {
            DB::table('suppliers')->where('id', $supplier->id)->update($supplierValues);
            $supplierId = (int) $supplier->id;
        } else {
            $supplierId = (int) DB::table('suppliers')->insertGetId($supplierValues + [
                'created_at' => $now,
            ]);
        }

        $products = DB::table('products')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%космос%'])
                    ->orWhereRaw('LOWER(COALESCE(h1, \'\')) LIKE ?', ['%космос%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%kosmos%']);
            })
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%котел%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%котёл%'])
                    ->orWhereRaw('LOWER(COALESCE(h1, \'\')) LIKE ?', ['%котел%'])
                    ->orWhereRaw('LOWER(COALESCE(h1, \'\')) LIKE ?', ['%котёл%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%kotel%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%kotly%']);
            })
            ->select(['id', 'sku', 'name'])
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        DB::table('products')->whereIn('id', $products->pluck('id'))->update([
            'is_active' => false,
            'in_stock' => false,
            'availability_status' => 'out_of_stock',
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        foreach ($products as $product) {
            $existing = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('product_id', $product->id)
                ->first();
            $article = $existing?->supplier_article ?: 'KOSMOS-'.$product->id;
            $values = [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'supplier_name' => $product->name,
                'source_url' => self::SOURCE_URL,
                'currency' => 'BYN',
                'in_stock' => false,
                'stock_quantity' => null,
                'stock_status' => 'preorder',
                'stock_text' => 'Под заказ, срок 10–14 дней',
                'delivery_days' => 14,
                'match_status' => 'matched',
                'match_confidence' => 'manual',
                'raw' => json_encode([
                    'source' => 'manager_confirmation',
                    'supplier' => 'Термопасс',
                    'delivery_term' => '10–14 дней',
                    'model_12_kw_discontinued' => true,
                ], JSON_UNESCAPED_UNICODE),
                'last_stock_synced_at' => $now,
                'last_synced_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('supplier_products')->where('id', $existing->id)->update($values);
            } else {
                DB::table('supplier_products')->insert($values + [
                    'supplier_id' => $supplierId,
                    'supplier_article' => $article,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Operational catalogue corrections intentionally remain in place.
    }
};
