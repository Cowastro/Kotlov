<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ELICON_CODE = 'elicon';
    private const ELICON_SYNC_KEY = 'elicon_gas_meters';
    private const ELICON_SOURCE_URL = 'https://elicon.by/product-category/bitovie_schetchiki_gaza/';
    private const TECHNICAL_ATTRIBUTE_NAMES = [
        'Поставщик',
        'Артикул поставщика',
        'Источник',
        'supplier',
        'supplier_article',
        'source_url',
        'source_wp_id',
    ];

    public function up(): void
    {
        $this->createSupplierProductsTable();
        $now = now();

        $supplierId = $this->ensureEliconSupplier($now);
        $syncId = $this->ensureEliconSync($now);

        $this->moveLegacyMappings($supplierId, $syncId, $now);
        $this->convertEliconProductSkus();
        $this->removeSupplierAttributesFromPublicCatalog();
        $this->removeSupplierKeysFromProductSpecs();
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }

    private function createSupplierProductsTable(): void
    {
        if (Schema::hasTable('supplier_products')) {
            return;
        }

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_sync_id')->nullable()->constrained('supplier_syncs')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_sku')->nullable()->index();
            $table->string('supplier_article')->index();
            $table->string('supplier_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_wp_id')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('BYN');
            $table->boolean('in_stock')->default(false);
            $table->string('match_status')->default('unmatched')->index();
            $table->string('match_confidence')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_article']);
        });
    }

    private function ensureEliconSupplier($now): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => self::ELICON_CODE],
            [
                'name' => 'Эликон',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::ELICON_SOURCE_URL,
                'notes' => 'Каталог бытовых счетчиков газа БЕЛОМО.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('suppliers')->where('code', self::ELICON_CODE)->value('id');
    }

    private function ensureEliconSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::ELICON_SYNC_KEY],
            [
                'name' => 'Эликон',
                'code' => self::ELICON_CODE,
                'title' => 'Эликон: счетчики газа',
                'description' => 'Обновляет цены, наличие, описания, характеристики и фотографии бытовых счетчиков газа.',
                'command' => 'supplier:sync-elicon-gas-meters',
                'source_url' => self::ELICON_SOURCE_URL,
                'image_disk_path' => 'img/products/elicon',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', self::ELICON_SYNC_KEY)->value('id');
    }

    private function moveLegacyMappings(int $supplierId, ?int $syncId, $now): void
    {
        if (! Schema::hasTable('supplier_product_mappings')) {
            return;
        }

        DB::table('supplier_product_mappings')
            ->where('supplier_code', self::ELICON_CODE)
            ->orderBy('id')
            ->each(function ($mapping) use ($supplierId, $syncId, $now) {
                $product = $mapping->product_id
                    ? DB::table('products')->where('id', $mapping->product_id)->first()
                    : null;

                DB::table('supplier_products')->updateOrInsert(
                    [
                        'supplier_id' => $supplierId,
                        'supplier_article' => $mapping->supplier_article,
                    ],
                    [
                        'supplier_sync_id' => $syncId,
                        'product_id' => $mapping->product_id,
                        'product_sku' => $product?->sku ?: $mapping->product_sku,
                        'supplier_name' => $mapping->supplier_name,
                        'source_url' => $this->sourceUrlFromNotes($mapping->notes),
                        'source_wp_id' => $this->wpIdFromNotes($mapping->notes),
                        'price' => $product?->price,
                        'currency' => $product?->currency ?: 'BYN',
                        'in_stock' => (bool) ($product?->in_stock ?? false),
                        'match_status' => $mapping->product_id ? 'matched' : 'unmatched',
                        'match_confidence' => $mapping->confidence,
                        'raw' => json_encode(['legacy_mapping_id' => $mapping->id], JSON_UNESCAPED_UNICODE),
                        'last_synced_at' => $mapping->updated_at ?: $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });
    }

    private function convertEliconProductSkus(): void
    {
        $next = $this->nextKotlovNumber();

        DB::table('products')
            ->where('sku', 'like', 'ELICON-%')
            ->orderBy('id')
            ->each(function ($product) use (&$next) {
                do {
                    $sku = sprintf('KOTLOV-%06d', $next++);
                } while (DB::table('products')->where('sku', $sku)->exists());

                DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
                DB::table('supplier_products')->where('product_id', $product->id)->update(['product_sku' => $sku]);
                DB::table('supplier_product_mappings')->where('product_id', $product->id)->update(['product_sku' => $sku]);
            });
    }

    private function removeSupplierAttributesFromPublicCatalog(): void
    {
        $attributeIds = DB::table('attributes')
            ->whereIn('name', self::TECHNICAL_ATTRIBUTE_NAMES)
            ->pluck('id');

        if ($attributeIds->isEmpty()) {
            return;
        }

        DB::table('product_attribute_values')->whereIn('attribute_id', $attributeIds)->delete();
        DB::table('attributes')->whereIn('id', $attributeIds)->delete();
    }

    private function removeSupplierKeysFromProductSpecs(): void
    {
        DB::table('supplier_products')
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->each(function ($productId) {
                $product = DB::table('products')->where('id', $productId)->first();
                $specs = json_decode((string) ($product->specs ?? ''), true);

                if (! is_array($specs)) {
                    return;
                }

                foreach (self::TECHNICAL_ATTRIBUTE_NAMES as $key) {
                    unset($specs[$key]);
                }

                DB::table('products')->where('id', $productId)->update([
                    'short_description' => null,
                    'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    private function sourceUrlFromNotes(?string $notes): string
    {
        if ($notes && preg_match('/url=([^;]+)/', $notes, $match)) {
            return trim($match[1]);
        }

        return self::ELICON_SOURCE_URL;
    }

    private function wpIdFromNotes(?string $notes): ?string
    {
        if ($notes && preg_match('/wp_id=([^;,\s]+)/', $notes, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function nextKotlovNumber(): int
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return max(0, (int) $max) + 1;
    }
};
