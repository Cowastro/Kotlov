<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Майтек Групп / Greolit PROFI: «с автоматикой» and «без автоматики» share one source page, so the old
 * source-page match shifted the links — the «99 без» card carried the «99 с» row, the «99 с» card the
 * «250 с» row, and the «250 с» card (named just «PROFI 250 кВт») lost its link. Put each row back.
 */
return new class extends Migration
{
    private const LINKS = [
        'greolit-kotel-greolit-profi-99-kvt-bez-avtomatiki' => 'Котел Greolit PROFI 99 кВт без автоматики',
        'greolit-kotel-greolit-profi-99-kvt-s-avtomatikoi' => 'Котел Greolit PROFI 99 кВт с автоматикой',
        'greolit-kotel-greolit-profi-250-kvt' => 'Котел Greolit PROFI 250 кВт с автоматикой',
    ];

    public function up(): void
    {
        $supplierId = DB::table('suppliers')->where('code', 'maitek-group')->value('id');
        if (! $supplierId) {
            return;
        }

        $hasCompact = Schema::hasColumn('supplier_products', 'supplier_article_compact');
        foreach (self::LINKS as $slug => $name) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'name']);
            if (! $product) {
                continue;
            }

            $article = 'Greolit '.$name;
            $normalized = $this->normArticle($article);
            // Another link may still hold this row's article: free it first (the unique key is supplier + article).
            DB::table('supplier_products')->where('supplier_id', $supplierId)
                ->where('supplier_article_normalized', $normalized)
                ->where(fn ($q) => $q->whereNull('product_id')->orWhere('product_id', '!=', $product->id))
                ->update(['supplier_article_normalized' => $normalized.' #'.$product->id, 'supplier_article' => $article.' #'.$product->id]);

            $payload = ['supplier_article' => $article, 'supplier_article_normalized' => $normalized, 'supplier_name' => $article, 'updated_at' => now()];
            if ($hasCompact) {
                $payload['supplier_article_compact'] = preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', $normalized) ?? '';
            }
            DB::table('supplier_products')->where('supplier_id', $supplierId)->where('product_id', $product->id)->update($payload);

            if (! str_contains(mb_strtolower((string) $product->name), 'автоматик')) {
                DB::table('products')->where('id', $product->id)->update(['name' => 'Greolit '.$name, 'h1' => 'Greolit '.$name, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        //
    }

    /** Same as SyncMaitekGroupCommand::normArticle(). */
    private function normArticle(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, ['А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'У' => 'Y', 'Х' => 'X']);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
};
