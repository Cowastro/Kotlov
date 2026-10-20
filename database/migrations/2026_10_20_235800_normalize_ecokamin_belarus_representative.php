<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMPANY = 'ООО «СанБизнесГруп»';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('brands')) {
            return;
        }

        $brandIds = DB::table('brands')
            ->where('slug', 'ecokamin')
            ->orWhere('name', 'ЭкоКамин')
            ->pluck('id');

        if ($brandIds->isEmpty()) {
            return;
        }

        DB::table('products')
            ->select(['id', 'specs', 'service_info'])
            ->whereIn('brand_id', $brandIds)
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $updates = [];

                    foreach (['specs', 'service_info'] as $column) {
                        if (! property_exists($product, $column)) {
                            continue;
                        }

                        $decoded = $this->decode($product->{$column});
                        if (! is_array($decoded)) {
                            continue;
                        }

                        $normalized = $this->normalizeCompanyData($decoded);
                        if ($normalized !== $decoded) {
                            $updates[$column] = json_encode(
                                $normalized,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            );
                        }
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('products')->where('id', $product->id)->update($updates);
                    }
                }
            }, 'id', 'id');

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // The former importer data was inconsistent, so it must not be restored.
    }

    private function decode(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function normalizeCompanyData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeCompanyData($value);
                continue;
            }

            if (is_string($key) && preg_match('/импорт[её]р\s+в\s+рб/ui', $key)) {
                $data[$key] = self::COMPANY;
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $data[$key] = preg_replace(
                '/ООО\s*[«\"“]?БАНЬКА[»\"”]?(?:\s*,?[^;\n]*)?/ui',
                self::COMPANY,
                $value,
            ) ?? $value;
        }

        return $data;
    }
};
