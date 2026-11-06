<?php

use App\Models\BlogPost;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const CALCULATOR_BLOCK = <<<'HTML'
<p>Хотите самостоятельно оценить стоимость дымохода? Укажите диаметр выхода, высоту и схему прокладки. Калькулятор подберёт комплектующие и покажет ориентировочную стоимость комплекта.</p>
<div class="local-shop-nav">
    <a href="/podbor-dymohoda">Рассчитать дымоход самостоятельно →</a>
</div>
HTML;

    public function up(): void
    {
        $post = $this->article();

        if (! $post || str_contains($post->content, '/podbor-dymohoda')) {
            return;
        }

        $heading = '<h2>Из чего складывается цена</h2>';
        $position = strpos($post->content, $heading);
        $block = self::CALCULATOR_BLOCK."\n\n";

        $post->content = $position === false
            ? rtrim($post->content)."\n\n".self::CALCULATOR_BLOCK
            : substr_replace($post->content, $block, $position, 0);

        $post->save();
    }

    public function down(): void
    {
        $post = $this->article();

        if ($post && str_contains($post->content, self::CALCULATOR_BLOCK)) {
            $post->content = str_replace(self::CALCULATOR_BLOCK, '', $post->content);
            $post->save();
        }
    }

    private function article(): ?BlogPost
    {
        return BlogPost::query()
            ->where('slug', 'montazh-dymohodov-iz-nerzhaveyki-lida')
            ->whereRelation('city', 'slug', 'lida')
            ->first();
    }
};
