<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        if (! Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            Schema::table('supplier_products', function (Blueprint $table) {
                $table->string('supplier_article_compact')->nullable()->after('supplier_article_normalized');
                $table->index(['supplier_id', 'supplier_article_compact'], 'sp_supplier_article_compact_idx');
            });
        }

        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            DB::table('supplier_products')
                ->whereNull('supplier_article_compact')
                ->orderBy('id')
                ->each(function ($row) {
                    DB::table('supplier_products')
                        ->where('id', $row->id)
                        ->update([
                            'supplier_article_compact' => $this->compactArticle($row->supplier_article_normalized ?: $row->supplier_article),
                        ]);
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_products') || ! Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            return;
        }

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropIndex('sp_supplier_article_compact_idx');
            $table->dropColumn('supplier_article_compact');
        });
    }

    private function compactArticle(?string $article): ?string
    {
        $article = mb_strtolower(trim((string) $article));
        $article = preg_replace('/[^a-z0-9]+/u', '', $article) ?? '';

        return $article !== '' ? $article : null;
    }
};
