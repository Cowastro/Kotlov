<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $products = [
            'pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1' => [
                'name' => 'Пеллетная горелка HOTTA Ceramik 20 кВт с Wi‑Fi',
                'h1' => 'Пеллетная горелка HOTTA Ceramik 20 кВт с Wi‑Fi',
                'stock_qty' => 1,
                'wrong_label' => 'Комплект 1',
            ],
            'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3' => [
                'name' => 'Пеллетная горелка HOTTA Ceramik 30 кВт с Wi‑Fi',
                'h1' => 'Пеллетная горелка HOTTA Ceramik 30 кВт с Wi‑Fi',
                'stock_qty' => 3,
                'wrong_label' => 'Комплект 3',
            ],
        ];

        foreach ($products as $slug => $data) {
            $product = DB::table('products')->where('slug', $slug)->first();

            if (! $product) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'name' => $data['name'],
                'h1' => $data['h1'],
                'stock_qty' => $data['stock_qty'],
                'content' => str_replace('Акционный '.$data['wrong_label'], 'Акционный комплект', (string) $product->content),
                'updated_at' => now(),
            ]);
        }

        $post = DB::table('blog_posts')
            ->where('slug', 'hotta-ceramik-20-30-kvt-rasprodazha-s-wifi-kontrollerom')
            ->first();

        if (! $post) {
            return;
        }

        $content = str_replace(
            [
                'HOTTA Ceramik 20 кВт, Комплект 1 — <strong>4 300 BYN</strong>; HOTTA Ceramik 30 кВт, Комплект 3 — <strong>4 600 BYN</strong>. Оба варианта находятся в наличии и продаются',
                'HOTTA Ceramik 20 кВт — Комплект 1',
                'HOTTA Ceramik 30 кВт — Комплект 3',
            ],
            [
                'HOTTA Ceramik 20 кВт — <strong>4 300 BYN</strong>, в наличии 1 штука; HOTTA Ceramik 30 кВт — <strong>4 600 BYN</strong>, в наличии 3 штуки. Оба варианта продаются',
                'HOTTA Ceramik 20 кВт — в наличии 1 штука',
                'HOTTA Ceramik 30 кВт — в наличии 3 штуки',
            ],
            (string) $post->content,
        );

        $content = str_replace('Оба варианта и продаются', 'Оба варианта продаются', $content);

        DB::table('blog_posts')->where('id', $post->id)->update([
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Stock quantities are real inventory data and must not be reverted.
    }
};
