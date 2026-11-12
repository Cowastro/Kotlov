<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LidaDachaStoveArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lida_dacha_article_is_published_in_the_lida_news(): void
    {
        config(['app.base_domain' => 'kotlov.by']);
        City::query()->updateOrCreate(['slug' => 'minsk'], ['name' => 'Минск', 'name_in' => 'в Минске', 'name_title' => 'Минске', 'is_active' => true, 'storefront_enabled' => true]);
        $lida = City::query()->updateOrCreate(['slug' => 'lida'], ['name' => 'Лида', 'name_in' => 'в Лиде', 'name_title' => 'Лиде', 'is_active' => true, 'storefront_enabled' => true]);
        $client = B2bClient::create(['user_id' => User::factory()->create()->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Магазин Лида']);
        PartnerBranch::create(['city_id' => $lida->id, 'b2b_client_id' => $client->id, 'name' => 'Магазин KOTLOV в Лиде', 'address' => 'г. Лида, ул. Калинина, 57',
            'phones' => ['+375333667609'], 'working_hours' => 'Вт–Сб 09:00–18:00', 'is_active' => true]);

        (require database_path('migrations/2026_11_12_110000_publish_lida_dacha_stove_chimney_article.php'))->up();
        (require database_path('migrations/2026_11_12_130000_lida_dacha_article_retouched_photos_and_cover.php'))->up();

        $this->get('https://lida.kotlov.by/news/dymohod-dlya-pechi-na-dache-lida')->assertOk()
            ->assertSee('Дымоход для печи на даче в Лиде: бюджетный монтаж через кровлю')
            ->assertSee('lida-dacha-cover.webp', false)
            ->assertSee('pech-i-dymohod-v-dome-2.webp', false)
            ->assertSee('dymohod-nad-krovley-2.webp', false)
            ->assertSee('визуализация финишной отделки')
            ->assertSee('Калинина, 57');
        $this->get('https://lida.kotlov.by/news')->assertOk()->assertSee('Дымоход для печи на даче в Лиде');
        $this->assertFileExists(public_path('img/blog/works/lida-dacha-pech/lida-dacha-cover.webp'));
        $this->assertFileExists(public_path('img/blog/works/lida-dacha-pech/pech-i-dymohod-v-dome-2.webp'));
        $this->assertFileExists(public_path('img/blog/works/lida-dacha-pech/dymohod-nad-krovley-2.webp'));
    }
}
