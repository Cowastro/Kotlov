<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $categoryId) {
            return;
        }

        DB::table('products')
            ->where('category_id', $categoryId)
            ->where(fn ($query) => $query
                ->where('meta_title', 'like', '%Эликон%')
                ->orWhere('meta_title', 'like', '%elicon%'))
            ->orderBy('id')
            ->get(['id', 'name', 'meta_title'])
            ->each(function (object $product): void {
                $title = preg_replace('/\s*[-—|]\s*Интернет-магазин\s+[«"]?Эликон[»"]?/ui', '', (string) $product->meta_title);
                $title = preg_replace('/\bЭликон\b|\belicon(?:\.by)?\b/ui', '', (string) $title);
                $title = trim(preg_replace('/\s{2,}/u', ' ', (string) $title) ?? (string) $title, " \t\n\r\0\x0B-|—");

                DB::table('products')->where('id', $product->id)->update([
                    'meta_title' => $title !== '' ? $title : $product->name.' — купить в Беларуси | KOTLOV',
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Конкурентный бренд в SEO-заголовки не возвращаем.
    }
};
