<?php

namespace Tests\Feature;

use App\Filament\Resources\PartnerBranches\Pages\ListPartnerBranches;
use App\Models\Category;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\PartnerBranchContactView;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerBranchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.base_domain' => 'kotlov.by']);
    }

    public function test_product_card_lists_the_branches_of_the_selected_city_without_phones(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $gomel = $this->city('gomel', 'Гомель');
        $product = $this->product();
        PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'ООО «СБГ Бай»', 'address' => 'г. Минск, ул. Селицкого, 39Б', 'phones' => ['+375 (29) 111-11-11'], 'sort_order' => 2]);
        PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'ООО «Тепло Минск»', 'phones' => ['+375 (29) 222-22-22'], 'sort_order' => 1]);
        PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'Скрытый филиал', 'phones' => ['+375 (29) 333-33-33'], 'is_active' => false]);
        PartnerBranch::query()->create(['city_id' => $gomel->id, 'name' => 'ООО «Гомель Тепло»', 'phones' => ['+375 (29) 444-44-44']]);

        $this->withUnencryptedCookie('selected_city', 'minsk')->get('https://kotlov.by/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSee('Где купить в Минске')
            ->assertSeeInOrder(['ООО «Тепло Минск»', 'ООО «СБГ Бай»'])
            ->assertSee('г. Минск, ул. Селицкого, 39Б')
            ->assertSee('Показать телефон')
            ->assertDontSee('+375 (29) 111-11-11')
            ->assertDontSee('Скрытый филиал')
            ->assertDontSee('ООО «Гомель Тепло»');

        $this->withUnencryptedCookie('selected_city', 'gomel')->get('https://kotlov.by/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()->assertSee('Где купить в Гомель')->assertSee('ООО «Гомель Тепло»')->assertDontSee('ООО «СБГ Бай»');
    }

    public function test_city_without_branches_shows_the_central_contact(): void
    {
        $this->city('minsk', 'Минск');
        $this->city('brest', 'Брест');
        $product = $this->product();

        $this->withUnencryptedCookie('selected_city', 'brest')->get('https://kotlov.by/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()->assertSee('Где купить в Брест')->assertSee('Заказ онлайн с доставкой в Брест');
    }

    public function test_phone_reveal_is_logged_and_hidden_branches_stay_hidden(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $product = $this->product();
        $branch = PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'ООО «СБГ Бай»', 'phones' => ['+375 (29) 111-11-11', '+375 (33) 555-55-55']]);
        $hidden = PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'Скрытый', 'phones' => ['+375 (29) 333-33-33'], 'is_active' => false]);

        $this->postJson(route('partner-branches.phone', $branch), ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('phones.0.display', '+375 (29) 111-11-11')
            ->assertJsonPath('phones.0.href', '+375291111111')
            ->assertJsonPath('phones.1.display', '+375 (33) 555-55-55');
        $this->assertDatabaseHas('partner_branch_contact_views', ['partner_branch_id' => $branch->id, 'product_id' => $product->id, 'page' => 'product']);

        $this->postJson(route('partner-branches.phone', $hidden))->assertNotFound();
        $this->assertSame(1, PartnerBranchContactView::query()->count());
    }

    public function test_admin_sees_branches_with_phone_reveal_counts(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $branch = PartnerBranch::query()->create(['city_id' => $minsk->id, 'name' => 'ООО «СБГ Бай»', 'phones' => ['+375 (29) 111-11-11']]);
        PartnerBranchContactView::query()->create(['partner_branch_id' => $branch->id, 'page' => 'product']);
        PartnerBranchContactView::query()->create(['partner_branch_id' => $branch->id, 'page' => 'product']);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Livewire::test(ListPartnerBranches::class)
            ->assertSee('ООО «СБГ Бай»')
            ->assertSee('Минск')
            ->assertSee('+375 (29) 111-11-11')
            ->assertSee('2');
    }

    private function city(string $slug, string $name): City
    {
        return City::query()->create([
            'slug' => $slug, 'name' => $name, 'name_in' => $name === 'Минск' ? 'в Минске' : 'в '.$name,
            'name_title' => $name, 'region' => $name, 'is_active' => true, 'storefront_enabled' => true,
        ]);
    }

    private function product(): Product
    {
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'wtb-kotly', 'is_active' => true]);

        return Product::query()->create([
            'category_id' => $category->id, 'name' => 'Котёл для теста', 'slug' => 'wtb-kotel',
            'sku' => 'WTB-1', 'price' => 1500, 'is_active' => true, 'is_archived' => false,
        ])->load('category');
    }
}
