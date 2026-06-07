<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeoSeeder extends Seeder
{
    public function run(): void
    {
        $total   = 0;
        $updated = 0;

        Product::with('brand')->chunk(200, function ($products) use (&$total, &$updated) {
            foreach ($products as $product) {
                $total++;
                $data      = [];
                $brand     = $product->brand?->name ?? '';
                $nameFull  = trim($brand . ' ' . $product->name);
                $nameLower = mb_strtolower($nameFull);

                // Фиксим %city% → оставляем как есть (контроллер подставит динамически)
                // Только заполняем пустые поля

                if (empty($product->meta_title)) {
                    $data['meta_title'] = "{$nameFull} — купить в %city% | KOTLOV";
                }

                if (empty($product->meta_description)) {
                    $priceStr = $product->price
                        ? ' Цена ' . number_format($product->price, 0, '.', ' ') . ' руб.'
                        : '';
                    $data['meta_description'] = "Купить {$nameLower} в %city%.{$priceStr} "
                        . "Доставка по всей Беларуси. Гарантия качества, консультация специалистов.";
                }

                if (empty($product->meta_keywords)) {
                    $data['meta_keywords'] = "{$nameFull}, купить {$nameLower}, "
                        . "{$nameLower} цена, {$nameLower} %city%, kotlov.by";
                }

                if (!empty($data)) {
                    $product->update($data);
                    $updated++;
                }
            }
        });

        $this->command->info("Products SEO: {$total} processed, {$updated} updated.");
    }
}
