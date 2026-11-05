<?php

namespace Tests\Feature;

use App\Filament\Resources\B2bClients\Pages\EditB2bClient;
use App\Filament\Resources\B2bClients\RelationManagers\PartnerBranchesRelationManager;
use App\Filament\Resources\PartnerBranches\Pages\ListPartnerBranches;
use App\Models\B2bClient;
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

    public function test_product_card_lists_approved_partners_points_of_the_selected_city_without_phones(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $gomel = $this->city('gomel', 'Гомель');
        $product = $this->product();
        $sbg = $this->partner('ООО «СБГ Бай»');
        $this->branch($sbg, $minsk, 'ООО «СБГ Бай»', ['address' => 'г. Минск, ул. Селицкого, 39Б', 'phones' => ['+375 (29) 111-11-11'], 'sort_order' => 2]);
        $this->branch($this->partner('ООО «Тепло Минск»'), $minsk, 'ООО «Тепло Минск»', ['sort_order' => 1, 'telegram_username' => 'teplominsk', 'viber_phone' => '+375291234567']);
        $this->branch($sbg, $minsk, 'Скрытая точка', ['is_active' => false]);
        $this->branch($this->partner('ООО «На проверке»', 'pending'), $minsk, 'Точка без доступа');
        $this->branch($this->partner('ООО «Гомель Тепло»'), $gomel, 'ООО «Гомель Тепло»');
        view()->share('citySelectorEnabled', true);

        $this->withUnencryptedCookie('selected_city', 'minsk')->get($this->url($product))
            ->assertOk()
            ->assertSee('Где купить в Минске')
            ->assertSeeInOrder(['ООО «Тепло Минск»', 'ООО «СБГ Бай»'])
            ->assertSee('г. Минск, ул. Селицкого, 39Б')
            ->assertSee('Показать телефон')
            ->assertSee('https://t.me/teplominsk', false)
            ->assertSee('viber://chat?number=%2B375291234567', false)
            ->assertSee('branch-social__icon', false)
            ->assertSee('Изменить город')
            ->assertSee('aria-label="Изменить город. Сейчас выбран: Минск"', false)
            ->assertSee('href="#citySelector"', false)
            ->assertSee('data-bs-toggle="offcanvas"', false)
            ->assertSee('Хотите, чтобы ваш магазин появился здесь?')
            ->assertSee('Стать партнёром')
            ->assertSee('href="'.route('partners').'"', false)
            ->assertSee('data-product-engineering-action', false)
            ->assertSee('Подбор инженером')
            ->assertDontSee('tf-product-engineering', false)
            ->assertDontSee('+375 (29) 111-11-11')
            ->assertDontSee('Скрытая точка')
            ->assertDontSee('Точка без доступа')
            ->assertDontSee('ООО «Гомель Тепло»');

        $this->withUnencryptedCookie('selected_city', 'gomel')->get($this->url($product))
            ->assertOk()->assertSee('ООО «Гомель Тепло»')->assertDontSee('ООО «СБГ Бай»');
    }

    public function test_city_without_points_shows_the_central_contact(): void
    {
        $this->city('minsk', 'Минск');
        $this->city('brest', 'Брест');
        $product = $this->product();

        $this->withUnencryptedCookie('selected_city', 'brest')->get($this->url($product))
            ->assertOk()->assertSee('Заказ онлайн с доставкой в Брест');
    }

    public function test_minsk_direct_contact_uses_sbg_bay_messengers_only(): void
    {
        $city = $this->city('minsk', 'Минск');
        $partner = $this->partner('ООО «СБГ бай»');
        $partner->update(['unp' => '193265863']);
        $branch = $this->branch($partner, $city, 'ООО «СБГ бай»', [
            'telegram_username' => 'sbgopt_bot', 'viber_phone' => '+375296322311',
            'whatsapp_phone' => '+375296322311', 'instagram_username' => 'pechkin_shop',
        ]);
        $this->branch($this->partner('Другой продавец'), $city, 'Другой продавец', ['sort_order' => 0, 'telegram_username' => 'another_store']);
        $html = view('partials.regional-quick-contact', ['currentCity' => $city])->render();
        foreach (['https://t.me/sbgopt_bot', 'viber://chat?number=%2B375296322311', 'https://wa.me/375296322311', 'https://www.instagram.com/pechkin_shop/'] as $link) {
            $this->assertStringContainsString($link, $html);
        }
        $this->assertStringNotContainsString('another_store', $html);
        $branch->update(['is_active' => false]);
        $this->assertStringNotContainsString('regional-quick-contact', view('partials.regional-quick-contact', ['currentCity' => $city])->render());
    }

    public function test_phone_reveal_is_logged_and_points_without_access_stay_hidden(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $product = $this->product();
        $partner = $this->partner('ООО «СБГ Бай»');
        $branch = $this->branch($partner, $minsk, 'ООО «СБГ Бай»', ['phones' => ['+375 (29) 111-11-11', '+375 (33) 555-55-55']]);
        $hidden = $this->branch($partner, $minsk, 'Скрытая', ['is_active' => false]);

        $this->postJson(route('partner-branches.phone', $branch), ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('phones.0.display', '+375 (29) 111-11-11')
            ->assertJsonPath('phones.0.href', '+375291111111')
            ->assertJsonPath('phones.1.display', '+375 (33) 555-55-55');
        $this->assertDatabaseHas('partner_branch_contact_views', ['partner_branch_id' => $branch->id, 'product_id' => $product->id, 'page' => 'product']);

        $this->postJson(route('partner-branches.phone', $hidden))->assertNotFound();
        $partner->update(['status' => 'paused']);
        $this->postJson(route('partner-branches.phone', $branch))->assertNotFound();
        $this->assertSame(1, PartnerBranchContactView::query()->count());
    }

    public function test_manager_assigns_a_point_from_the_partner_card_only_after_approval(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $pending = $this->partner('ООО «На проверке»', 'pending');
        Livewire::test(PartnerBranchesRelationManager::class, ['ownerRecord' => $pending, 'pageClass' => EditB2bClient::class])
            ->assertSee('Сначала одобрите B2B-доступ')
            ->assertTableActionHidden('create');

        $partner = $this->partner('ООО «СБГ Бай»');
        Livewire::test(PartnerBranchesRelationManager::class, ['ownerRecord' => $partner, 'pageClass' => EditB2bClient::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'city_id' => $minsk->id, 'name' => 'ООО «СБГ Бай»', 'address' => 'г. Минск, ул. Селицкого, 39Б',
                'phones' => ['+375 (29) 111-11-11'], 'sort_order' => 100, 'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($partner->id, PartnerBranch::query()->sole()->b2b_client_id);
    }

    public function test_admin_list_shows_points_with_reveal_counts_and_partner_sees_them_in_cabinet(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $partner = $this->partner('ООО «СБГ Бай»');
        $branch = $this->branch($partner, $minsk, 'ООО «СБГ Бай» — Селицкого', ['phones' => ['+375 (29) 111-11-11']]);
        PartnerBranchContactView::query()->create(['partner_branch_id' => $branch->id, 'page' => 'product']);
        PartnerBranchContactView::query()->create(['partner_branch_id' => $branch->id, 'page' => 'product']);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Livewire::test(ListPartnerBranches::class)
            ->assertSee('ООО «СБГ Бай» — Селицкого')->assertSee('Минск')->assertSee('+375 (29) 111-11-11');

        $this->actingAs($partner->user)->get(route('b2b.cabinet'))
            ->assertOk()
            ->assertSee('Мои точки продаж')
            ->assertSee('ООО «СБГ Бай» — Селицкого')
            ->assertSee('показов телефона за 30 дней');
    }

    public function test_one_partner_can_have_points_in_several_cities(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $borisov = $this->city('borisov', 'Борисов');
        $product = $this->product();
        $sbg = $this->partner('ООО «СБГ бай»');
        $this->branch($sbg, $minsk, 'ООО «СБГ бай»');
        $this->branch($sbg, $borisov, 'Магазин «Печкiн»');

        $this->withUnencryptedCookie('selected_city', 'borisov')->get($this->url($product))
            ->assertOk()->assertSee('Магазин «Печкiн»')->assertDontSee('ООО «СБГ бай»</strong>', false);
        $this->assertSame(2, $sbg->branches()->count());
    }

    public function test_sbg_bay_is_connected_with_points_in_minsk_and_borisov_once(): void
    {
        $this->city('minsk', 'Минск');
        $this->city('borisov', 'Борисов');
        $migration = require database_path('migrations/2026_11_03_250000_connect_sbg_bay_partner_branches.php');

        $migration->up();
        $migration->up();

        $client = B2bClient::query()->where('unp', '193265863')->sole();
        $this->assertSame('approved', $client->status);
        $this->assertSame('ООО «СБГ бай»', $client->company_name);
        $this->assertSame(['Минск', 'Борисов'], $client->branches()->with('city')->orderBy('id')->get()->pluck('city.name')->all());
        $this->assertSame(['+375 (29) 144-63-14'], $client->branches()->whereHas('city', fn ($q) => $q->where('slug', 'borisov'))->sole()->phones);
    }

    public function test_footer_shows_the_points_of_the_selected_city_instead_of_one_store(): void
    {
        $minsk = $this->city('minsk', 'Минск');
        $this->city('brest', 'Брест');
        $this->branch($this->partner('ООО «СБГ бай»'), $minsk, 'ООО «СБГ бай»', ['address' => 'г. Минск, ул. Селицкого, 39Б', 'phones' => ['+375 (29) 111-11-11']]);

        $this->withUnencryptedCookie('selected_city', 'minsk')->get('https://kotlov.by/contacts')
            ->assertOk()
            ->assertSee('ГДЕ КУПИТЬ В МИНСКЕ')
            ->assertSee('ООО «СБГ бай»')
            ->assertDontSee('НАШ МАГАЗИН')
            ->assertDontSee('+375 (29) 111-11-11');

        $this->withUnencryptedCookie('selected_city', 'brest')->get('https://kotlov.by/contacts')
            ->assertOk()
            ->assertSee('ЗАКАЗ ОНЛАЙН')
            ->assertSee('Консультации и заказы с доставкой в Брест')
            ->assertDontSee('ООО «СБГ бай»');
    }

    private function partner(string $company, string $status = 'approved'): B2bClient
    {
        $user = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => $status === 'approved', 'is_active' => true]);

        return B2bClient::query()->create([
            'user_id' => $user->id, 'status' => $status, 'partner_type' => 'wholesale',
            'company_name' => $company, 'contact_name' => $user->name, 'contact_email' => $user->email,
        ])->load('user');
    }

    private function branch(B2bClient $partner, City $city, string $name, array $extra = []): PartnerBranch
    {
        return PartnerBranch::query()->create(array_merge([
            'b2b_client_id' => $partner->id, 'city_id' => $city->id, 'name' => $name, 'phones' => ['+375 (29) 000-00-00'],
        ], $extra));
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

    private function url(Product $product): string
    {
        return 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug;
    }
}
