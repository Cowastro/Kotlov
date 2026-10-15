<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'documents')) {
            return;
        }

        $skus = array_map(fn (int $suffix): string => 'PS-011.'.$suffix, range(540, 556));

        DB::table('products')->whereIn('sku', $skus)->orderBy('id')->each(function ($product): void {
            $documents = json_decode((string) ($product->documents ?? '[]'), true) ?: [];
            $filtered = array_values(array_filter($documents, function ($document): bool {
                $url = is_array($document) ? ($document['url'] ?? '') : $document;
                $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

                return $host !== 'pkmercury.by' && ! str_ends_with($host, '.pkmercury.by');
            }));

            if ($filtered !== $documents) {
                DB::table('products')->where('id', $product->id)->update([
                    'documents' => json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Public manufacturer links are intentionally not restored.
    }
};
