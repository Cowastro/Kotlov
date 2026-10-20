<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PORTFOLIO_URL = 'https://www.ecokamin.ru/portfolio/';

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->addPortfolio(
            [
                'kaminnaya-topka-ekokamin-alfa-700-tri-stekla-podovaya',
                'kaminnaya-topka-ekokamin-alfa-700-tri-stekla-chernyiy-shamot-podovaya',
            ],
            'alpha-700-three-glass',
            'АЛЬФА 700 с трёхсторонним остеклением в интерьере',
            'Реальная установка модели серии АЛЬФА 700 показывает обзор огня с трёх сторон и пропорции готового каминного короба. Оттенок футеровки конкретной комплектации может отличаться от показанного проекта.',
            [
                'img/products/ecokamin/portfolio/alpha-700-three-glass-1.webp',
                'img/products/ecokamin/portfolio/alpha-700-three-glass-2.webp',
            ],
        );

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // Published marketing content and galleries are intentionally durable.
    }

    private function addPortfolio(
        array $slugs,
        string $key,
        string $title,
        string $description,
        array $portfolioImages,
    ): void {
        $products = DB::table('products')
            ->whereIn('slug', $slugs)
            ->get(['id', 'name', 'images', 'content']);

        foreach ($products as $product) {
            $decoded = is_string($product->images) ? json_decode($product->images, true) : $product->images;
            $images = collect([...(is_array($decoded) ? $decoded : []), ...$portfolioImages])
                ->filter(fn ($image): bool => is_string($image) && trim($image) !== '')
                ->unique()
                ->values()
                ->all();

            $content = (string) ($product->content ?? '');
            $marker = 'data-ecokamin-portfolio="'.$key.'"';

            if (! str_contains($content, $marker)) {
                $figures = collect($portfolioImages)
                    ->map(function (string $image, int $index) use ($product): string {
                        $number = $index + 1;

                        return '<figure><img loading="lazy" src="/'.e($image).'" alt="'.e((string) $product->name).' — реальный пример установки, фото '.$number.'"><figcaption>Реальный объект из портфолио производителя.</figcaption></figure>';
                    })
                    ->implode("\n");

                $content = rtrim($content)."\n\n".<<<HTML
<section data-ecokamin-portfolio="{$key}">
<h2>{$title}</h2>
<p>{$description}</p>
{$figures}
<p><small>Источник фотографий: <a href="https://www.ecokamin.ru/portfolio/" rel="nofollow noopener" target="_blank">официальное портфолио ЭкоКамин</a>. Фотографии показывают пример реализованного объекта; монтажное решение проектируется индивидуально.</small></p>
<p><a href="/blog/realnye-kaminy-ecokamin-v-interere-portfolio"><strong>Смотреть другие реальные проекты ЭкоКамин →</strong></a></p>
</section>
HTML;
            }

            DB::table('products')->where('id', $product->id)->update([
                'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content' => $content,
                'updated_at' => now(),
            ]);
        }
    }
};
