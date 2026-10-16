<?php

use App\Console\Commands\PublishKotlovCeramicBoilerCommand as Package;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $product = Product::query()->where('sku', Package::SKU)->where('slug', Package::SLUG)->first();
        if (! $product) {
            return;
        }
        $obsolete = [
            '/img/products/kotlov-xo-boiler-95/kit-visualization-v4.png',
            '/img/products/kotlov-xo-boiler-95/kit-visualization-v5.png',
            '/img/products/kotlov-xo-boiler-95/installation-retouched-v1.png',
        ];
        $images = array_values(array_unique([
            ...Package::packageImages(),
            ...array_values(array_diff((array) $product->images, $obsolete)),
        ]));
        foreach (Package::packageImages() as $image) {
            if (! is_file(public_path(ltrim($image, '/')))) {
                throw new RuntimeException('Missing confirmed manufacturer photo: '.$image);
            }
        }
        $oldNote = '<p><small>Главное изображение — визуализация компоновки комплекта, не фотография готовой установки. Реальное фото установки в галерее показывает пример компоновки базового котла и бункера; горелка и автоматика на этом фото отличаются от комплектации KOTLOV. Отдельные фотографии горелки, топки и контроллера показывают оборудование серии Ceramic PRO / XO. Исполнение и размещение узлов согласуются при заказе.</small></p>';
        $content = str_replace($oldNote, '', (string) $product->content);
        if (! str_contains($content, 'data-kotlov-95-photos=')) {
            $content = Package::PHOTO_NOTE."\n".$content;
        }
        // Content-only update: never republish price, stock, supplier data or 1C links.
        $product->fill(['images' => $images, 'content' => $content]);
        if ($product->isDirty()) {
            $product->save();
        }
    }

    public function down(): void
    {
        // Do not restore rejected imagery or discard later editorial changes.
    }
};
