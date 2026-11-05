<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalStoreDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_approved_city_shops_appear_on_home_and_directory(): void
    {
        config(['app.base_domain' => 'kotlov.by']);
        City::create(['slug' => 'minsk', 'name' => 'Минск', 'name_in' => 'в Минске', 'name_title' => 'Минске', 'is_active' => true, 'storefront_enabled' => true]);
        foreach ([['lida', 'Лида', true, 'approved'], ['borisov', 'Борисов', false, 'approved'], ['grodno', 'Гродно', true, 'pending']] as [$slug, $name, $enabled, $status]) {
            $city = City::create(['slug' => $slug, 'name' => $name, 'name_in' => 'в '.$name, 'name_title' => $name, 'is_active' => true, 'storefront_enabled' => $enabled]);
            $user = User::factory()->create();
            $client = B2bClient::create(['user_id' => $user->id, 'status' => $status, 'partner_type' => 'wholesale', 'company_name' => 'Магазин '.$name]);
            PartnerBranch::create(['city_id' => $city->id, 'b2b_client_id' => $client->id, 'name' => 'Магазин '.$name, 'address' => 'Адрес '.$name, 'phones' => ['+375 (29) 111-22-33'], 'working_hours' => 'Вт–Сб 09:00–18:00', 'is_active' => true]);
        }

        foreach (['/', '/regions'] as $path) {
            $this->get('https://kotlov.by'.$path)->assertOk()
                ->assertSee('KOTLOV в регионах')
                ->assertSee('https://lida.kotlov.by/', false)
                ->assertSee('Адрес Лида')
                ->assertSee('Стать B2B-партнёром')
                ->assertDontSee('Адрес Борисов')
                ->assertDontSee('Адрес Гродно');
        }

        $this->get('https://kotlov.by/regions')
            ->assertSee('<link rel="canonical" href="https://kotlov.by/regions">', false);
        $this->get('https://lida.kotlov.by/regions')->assertOk()
            ->assertSee('<link rel="canonical" href="https://kotlov.by/regions">', false);
    }
}
