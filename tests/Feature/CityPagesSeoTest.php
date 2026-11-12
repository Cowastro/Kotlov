<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityPagesSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_and_delivery_have_their_own_titles(): void
    {
        $this->get('/contacts')->assertOk()->assertSee('<title>Контакты KOTLOV в Минске — адрес, телефоны, режим работы</title>', false);
        $this->get('/dostavka')->assertOk()->assertSee('<title>Доставка и оплата в Минске — котлы, печи, камины | KOTLOV</title>', false);
    }

    public function test_the_accessories_category_no_longer_calls_itself_bath_stoves(): void
    {
        Category::query()->updateOrCreate(['slug' => 'pechi-dlya-bani'], ['name' => 'Аксессуары для парной', 'h1' => 'Печи для бани',
            'meta_title' => 'Каталог печей для бани', 'is_active' => true]);
        (require database_path('migrations/2026_11_12_190000_rename_steam_room_accessories_category.php'))->up();

        $category = Category::query()->where('slug', 'pechi-dlya-bani')->first();
        $this->assertSame('Аксессуары для парной', $category->h1);
        $this->assertStringContainsString('%city%', $category->meta_title);
        $this->assertStringNotContainsString('Каталог печей', (string) $category->meta_description);
    }
}
