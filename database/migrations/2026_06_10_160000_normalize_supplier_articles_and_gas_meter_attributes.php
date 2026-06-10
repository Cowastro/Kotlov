<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORY_ID = 96;

    public function up(): void
    {
        if (Schema::hasTable('supplier_products') && ! Schema::hasColumn('supplier_products', 'supplier_article_normalized')) {
            Schema::table('supplier_products', function (Blueprint $table) {
                $table->string('supplier_article_normalized')->nullable()->after('supplier_article')->index();
            });
        }

        if (Schema::hasColumn('supplier_products', 'supplier_article_normalized')) {
            DB::table('supplier_products')
                ->orderBy('id')
                ->each(function ($row) {
                    DB::table('supplier_products')->where('id', $row->id)->update([
                        'supplier_article_normalized' => $this->normalizeArticle($row->supplier_article),
                    ]);
                });
        }

        $this->renameGasSizeAttribute();
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_products') && Schema::hasColumn('supplier_products', 'supplier_article_normalized')) {
            Schema::table('supplier_products', function (Blueprint $table) {
                $table->dropColumn('supplier_article_normalized');
            });
        }
    }

    private function renameGasSizeAttribute(): void
    {
        $old = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', 'Типоразмер')
            ->first();

        if (! $old) {
            return;
        }

        $new = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', 'Номинальный расход')
            ->first();

        if (! $new) {
            DB::table('attributes')->where('id', $old->id)->update([
                'name' => 'Номинальный расход',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('product_attribute_values')
            ->where('attribute_id', $old->id)
            ->orderBy('id')
            ->each(function ($value) use ($new) {
                $existing = DB::table('product_attribute_values')
                    ->where('product_id', $value->product_id)
                    ->where('attribute_id', $new->id)
                    ->first();

                if ($existing) {
                    DB::table('product_attribute_values')->where('id', $value->id)->delete();
                    return;
                }

                DB::table('product_attribute_values')->where('id', $value->id)->update([
                    'attribute_id' => $new->id,
                    'updated_at' => now(),
                ]);
            });

        DB::table('attributes')->where('id', $old->id)->delete();
    }

    private function normalizeArticle(?string $article): ?string
    {
        if ($article === null) {
            return null;
        }

        $article = str_replace(['–', '—', '−'], '-', $article);
        $article = preg_replace('/\s+/u', '', $article) ?? $article;

        return mb_strtoupper(trim($article));
    }
};
