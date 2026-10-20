<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCT_SKU = 'PS-010.108';

    private const OFFICIAL_PRODUCT_URL = 'https://wilo.com/rs/sr/Wilo-pumpe-i-sistemi/sr/proizvodi/star-z-nova_id146/4132760';

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('products')) {
            DB::table('products')
                ->where(function ($query): void {
                    $query->where('sku', self::PRODUCT_SKU)
                        ->orWhere('name', 'like', '%4132760%');
                })
                ->update([
                    'price' => 0,
                    'price_old' => null,
                    'requires_price_confirmation' => true,
                    'in_stock' => false,
                    'stock_qty' => null,
                    'availability_status' => 'check',
                    'content_source_url' => self::OFFICIAL_PRODUCT_URL,
                    'updated_at' => $now,
                ]);
        }

        if (! Schema::hasTable('suppliers')) {
            Cache::forget('home:data');

            return;
        }

        $supplierPayload = [
            'name' => 'ИООО «ВИЛО БЕЛ»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => '+375 17 396-34-47 · +375 29 346-07-93 · wilo@wilo.by',
            'notes' => 'Официальный представитель и первый импортёр Wilo в Беларуси. Для артикула 4132760 закупочная цена и наличие не подтверждены на 20.10.2026: не создавать канал товара и не открывать заказ до получения коммерческого предложения.',
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('suppliers', 'communication_channels')) {
            $supplierPayload['communication_channels'] = json_encode([
                ['type' => 'phone', 'value' => '+375 17 396-34-47', 'label' => 'Отдел оптовых поставок дилерам'],
                ['type' => 'phone', 'value' => '+375 29 346-07-93', 'label' => 'Отдел оптовых поставок дилерам'],
                ['type' => 'email', 'value' => 'wilo@wilo.by', 'label' => 'Общий отдел продаж'],
                ['type' => 'website', 'value' => 'https://wilo.by/', 'label' => 'Официальный сайт'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $supplierId = DB::table('suppliers')->where('code', 'wilo-bel')->value('id');
        if ($supplierId) {
            DB::table('suppliers')->where('id', $supplierId)->update($supplierPayload);
        } else {
            $supplierId = DB::table('suppliers')->insertGetId([
                'code' => 'wilo-bel',
                ...$supplierPayload,
                'created_at' => $now,
            ]);
        }

        if (Schema::hasTable('brand_supplier') && Schema::hasTable('brands')) {
            $brandId = DB::table('brands')
                ->where('slug', 'wilo')
                ->orWhereRaw('LOWER(name) = ?', ['wilo'])
                ->value('id');

            if ($brandId) {
                DB::table('brand_supplier')->updateOrInsert(
                    ['brand_id' => $brandId, 'supplier_id' => $supplierId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // The safety hold and verified supplier-directory record are operational data.
    }
};
