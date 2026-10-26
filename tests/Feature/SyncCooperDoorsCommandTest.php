<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCooperDoorsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function door(int $id, string $name, string $series, int $price, string $short): array
    {
        return [
            'id' => $id, 'name' => $name, 'permalink' => 'https://cooper.by/product/door-'.$id.'/',
            'prices' => ['price' => (string) ($price * 100), 'currency_minor_unit' => 2],
            'categories' => [['name' => 'Серия '.$series]], 'is_in_stock' => true, 'images' => [],
            'short_description' => $short,
        ];
    }

    public function test_first_import_creates_cards_with_retail_price_and_no_cost(): void
    {
        Category::query()->create(['name' => 'Двери для бани и сауны', 'slug' => 'dveri-dlya-ban-i-saun', 'is_active' => true]);
        Http::fake(['cooper.by/wp-json/*' => Http::response([
            $this->door(36360, 'Дверь стеклянная для бани 200*80 Бронза матовая', 'Стандарт', 519,
                '<ul><li>Дверная коробка (40*75мм) — ОЛЬХА;</li><li>Закаленное стекло — 8 мм;</li><li>Петли овальные — 3 шт;</li></ul>'),
            $this->door(36385, 'Дверь стеклянная для бани PRO 200*81,5 Бронза', 'PRO', 882, '<p>Петли усиленные G60 — 3 шт;</p>'),
            ['id' => 36422, 'name' => 'Дверная коробка из Ольхи', 'permalink' => 'https://cooper.by/product/box/', 'prices' => ['price' => '0', 'currency_minor_unit' => 2],
                'categories' => [['name' => 'Дверная коробка из ОЛЬХИ']], 'is_in_stock' => true, 'images' => [], 'short_description' => ''],
        ])]);

        $this->artisan('supplier:sync-cooper-doors')->assertSuccessful();
        $this->assertSame(0, Product::query()->where('sku', 'like', 'COOPER-%')->count());

        $this->artisan('supplier:sync-cooper-doors --apply --create-new')->assertSuccessful();

        $door = Product::query()->where('sku', 'COOPER-36360')->firstOrFail();
        $this->assertSame('Дверь стеклянная для бани Cooper Стандарт 200×80, бронза матовая', $door->name);
        $this->assertSame(519.0, (float) $door->price);
        $this->assertSame('Cooper', $door->brand->name);
        $this->assertContains(['key' => 'Материал коробки', 'value' => 'Ольха'], $door->specs);
        $this->assertContains(['key' => 'Поверхность стекла', 'value' => 'Матовая'], $door->specs);
        $this->assertContains(['key' => 'Стекло', 'value' => 'Закалённое, 8 мм'], $door->specs);
        $this->assertSame('Дверь стеклянная для бани Cooper PRO 200×81,5, бронза', Product::query()->where('sku', 'COOPER-36385')->value('name'));

        $link = SupplierProduct::query()->where('product_id', $door->id)->firstOrFail();
        $this->assertNull($link->price_byn);
        $this->assertSame('saunaopt', $link->supplier->code);
        $this->assertSame(2, Product::query()->where('sku', 'like', 'COOPER-%')->count());

        // The daily run updates; it never creates twice.
        $this->artisan('supplier:sync-cooper-doors --apply --create-new')->assertSuccessful();
        $this->assertSame(2, Product::query()->where('sku', 'like', 'COOPER-%')->count());
        $this->assertSame(2, SupplierProduct::query()->where('supplier_article', 'like', 'cooper-%')->count());
    }
}
