<?php

use App\Console\Commands\PublishKotlovCeramicBoilerCommand;
use App\Support\Catalog\Kotlov95SeoContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $product = DB::table('products')->where('sku', PublishKotlovCeramicBoilerCommand::SKU)
                ->where('slug', PublishKotlovCeramicBoilerCommand::SLUG)->first();

            if ($product && ! str_contains((string) $product->content, 'data-kotlov-95-selection=')) {
                DB::table('products')->where('id', $product->id)->update([
                    'content' => $product->content."\n".Kotlov95SeoContent::productBlock(),
                    'updated_at' => now(),
                ]);
            }

            // Never overwrite an editorial article or a draft with the same slug.
            if (DB::table('blog_posts')->where('slug', Kotlov95SeoContent::ARTICLE_SLUG)->exists()) {
                return;
            }

            $categoryId = DB::table('blog_categories')->where('slug', 'kotly-i-otoplenie')->value('id');
            if (! $categoryId) {
                $categoryId = DB::table('blog_categories')->insertGetId([
                    'name' => 'Котлы и отопление', 'slug' => 'kotly-i-otoplenie',
                    'sort_order' => 20, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('blog_posts')->insert([
                'category_id' => $categoryId,
                'title' => 'Пеллетный котёл KOTLOV 95 кВт: как подобрать и подготовить котельную',
                'slug' => Kotlov95SeoContent::ARTICLE_SLUG,
                'excerpt' => 'Практический разбор комплекта XO Ceramic PRO: бункер 1500 л, Wi-Fi по умолчанию, зольник, дымоход и обслуживание. Что проверить для склада, цеха или мастерской.',
                'content' => Kotlov95SeoContent::article(),
                'cover_image' => 'img/products/kotlov-xo-boiler-95/installation-current-wide-original.jpg',
                'images' => json_encode(['img/products/kotlov-xo-boiler-95/installation-current-wide-original.jpg', 'img/products/kotlov-xo-boiler-95/base-boiler-cutaway.png'], JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['пеллетный котёл 95 кВт', 'KOTLOV XO', 'Ceramic PRO', 'пеллетная котельная', 'отопление склада', 'отопление производства'], JSON_UNESCAPED_UNICODE),
                'is_published' => true, 'published_at' => now(),
                'meta_title' => 'Пеллетный котёл KOTLOV 95 кВт: подбор котельной и бункера',
                'meta_description' => 'Как подготовить котельную для KOTLOV XO Ceramic PRO 95 кВт: бункер 1500 л, Wi-Fi, очистка зольника и дымоход. Состав комплекта и данные для подбора.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $product = DB::table('products')->where('sku', PublishKotlovCeramicBoilerCommand::SKU)
            ->where('slug', PublishKotlovCeramicBoilerCommand::SLUG)->first();

        if ($product && str_contains((string) $product->content, Kotlov95SeoContent::productBlock())) {
            DB::table('products')->where('id', $product->id)->update([
                'content' => str_replace(Kotlov95SeoContent::productBlock(), '', (string) $product->content),
                'updated_at' => now(),
            ]);
        }

        // Editorial content is intentionally retained: a rollback must not erase
        // articles, subsequent user edits, publication choices or their category.
    }
};
