<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $product = DB::table('products')
                ->where('slug', 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt')
                ->where('is_sale', true)
                ->lockForUpdate()
                ->first();

            if ($product) {
                $price = (float) $product->price;
                if ($product->price_old > 0 && round($product->price_old * 0.9, 2) === $price) {
                    $price = $product->price_old;
                }
                DB::table('products')->where('id', $product->id)->update([
                    'price' => $price, 'price_old' => null, 'is_sale' => false, 'updated_at' => now(),
                ]);
            }

            $banner = DB::table('banners')->where('image', 'banners/pellet-burner-clearance-desktop.svg');
            foreach ($banner->get() as $row) {
                $description = str_replace(['Ceramic PRO 100 кВт — 12 960 BYN, ', 'Ceramic PRO 100 кВт −10%, '], '', (string) $row->description);
                if ($description !== $row->description) {
                    DB::table('banners')->where('id', $row->id)->update(['description' => $description]);
                }
            }

            $article = DB::table('blog_posts')->where('slug', 'pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro');
            $content = $article->value('content');
            if ($content !== null) {
                $content = strtr($content, [
                    'Сейчас модель находится в наличии и участвует в акции со скидкой 10%. Но до заказа' => 'До заказа',
                    'В акционной комплектации' => 'В комплектации',
                    'Акционный комплект включает' => 'Комплект включает',
                    'Акционная цена KOTLOV XO Ceramic PRO 100 кВт — 12 960 BYN, то есть на 2 040 BYN ниже, при этом Wi‑Fi уже входит в комплект.' => 'В комплект KOTLOV XO Ceramic PRO 100 кВт входит Wi‑Fi.',
                    'Акция и инженерный расчёт' => 'Инженерный расчёт',
                    'На модель 100 кВт действует скидка 10% до продажи выделенного акционного остатка. Условия, текущая цена и форма заявки размещены на <a href="/akcii/kotlov-xo-ceramic-pro"><strong>странице акции KOTLOV XO Ceramic PRO</strong></a>.' => 'Актуальная цена указана в <a href="/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt">карточке горелки</a>.',
                ]);
                $article->update(['content' => $content, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Ending a commercial offer is permanent.
    }
};
