<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductXoControllerManualLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_xo_ceramic_pro_100_page_shows_the_controller_manual_in_description_and_documents(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'pelletnye-gorelki'],
            [
                'parent_id' => 0,
                'name' => 'Пеллетные горелки',
                'is_active' => true,
            ]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт',
            'slug' => 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
            'sku' => 'PS-012.203',
            'price' => 14400,
            'content' => '<p>Описание горелки без ссылки на руководство.</p>',
            'documents' => null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);

        $this->get('https://kotlov.by/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt')
            ->assertOk()
            ->assertSee('Инструкция контроллера XO 1.0S/2.0S (PDF)')
            ->assertSee('href="/docs/kotlov-xo-controller-1-2os-ru.pdf"', false)
            ->assertSee('href="#documents"', false);
    }
}
