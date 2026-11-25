<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaunaStoveHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_stoves_share_a_storefront_and_old_accessory_links_follow_the_product(): void
    {
        Category::where('slug', 'pechi-dlya-bani')->update(['slug' => 'prior-test-hub']);
        Category::where('slug', 'aksessuary-dlya-parnoy')->update(['slug' => 'pechi-dlya-bani']);
        $supplier = Supplier::create(['code' => 'sauna-test', 'name' => 'Поставщик печей', 'is_active' => true]);
        $items = [
            'pechi-dlya-bani' => ['Аксессуары для парной', 'Длинный черпак', 75],
            'drovianye-peci-bannye' => ['Дровяные печи', 'Банная печь Дрова', 550],
            'elektrokamenki' => ['Электрокаменки', 'Электрокаменка Тепло', 825],
        ];
        foreach ($items as $slug => [$categoryName, $name, $price]) {
            $category = Category::updateOrCreate(['slug' => $slug], ['name' => $categoryName, 'is_active' => true]);
            $product = Product::create([
                'name' => $name, 'slug' => 'fixture-'.$slug, 'sku' => 'fixture-'.$slug, 'category_id' => $category->id,
                'is_active' => true, 'is_archived' => false, 'in_stock' => true, 'price' => $price,
            ]);
            $product->supplierProducts()->create([
                'supplier_id' => $supplier->id, 'supplier_article' => $product->sku,
                'price_byn' => $price, 'stock_quantity' => 8, 'in_stock' => true,
                'last_synced_at' => now(), 'last_stock_synced_at' => now(),
            ]);
        }

        (require database_path('migrations/2026_11_25_220000_separate_sauna_stove_hub_and_accessories.php'))->up();

        $this->get('https://kotlov.by/pechi-dlya-bani')->assertOk()
            ->assertSeeText('Печи для бани и сауны')->assertSeeText('Банная печь Дрова')
            ->assertSeeText('Электрокаменка Тепло')->assertSeeText('550.00 BYN')->assertSeeText('825.00 BYN')->assertSeeText('В наличии')
            ->assertDontSeeText('Длинный черпак')
            ->assertSeeInOrder(['id="sauna-group-0"', 'Банная печь Дрова', '550,00 BYN', 'В наличии', 'id="sauna-group-1"', 'Электрокаменка Тепло', '825,00 BYN', 'В наличии'], false);
        $this->get('https://kotlov.by/aksessuary-dlya-parnoy')->assertOk()->assertSeeText('Длинный черпак');
        $destination = 'https://kotlov.by/aksessuary-dlya-parnoy/fixture-pechi-dlya-bani?ref=old';
        $this->get('https://kotlov.by/pechi-dlya-bani/fixture-pechi-dlya-bani?ref=old')
            ->assertStatus(301)->assertRedirect($destination);
        $this->get($destination)->assertOk()->assertSeeText('Длинный черпак');
    }
}
