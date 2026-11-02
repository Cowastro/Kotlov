<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityContactProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityContactSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.base_domain' => 'kotlov.by']);
        $this->city('minsk', 'Минск', true);
    }

    public function test_enabled_city_can_be_selected_without_leaving_primary_domain(): void
    {
        $gomel = $this->city('gomel', 'Гомель', true);
        CityContactProfile::query()->create([
            'city_id' => $gomel->id,
            'company_name' => 'ООО «Гомель Тепло»',
            'phones' => ['+375 (29) 111-22-33'],
            'email' => 'gomel@example.by',
            'address' => 'Беларусь, г. Гомель, ул. Тестовая, 1',
            'map_url' => 'https://maps.example/gomel',
            'map_embed_url' => 'https://maps.example/gomel/embed',
            'is_active' => true,
        ]);

        $this->get('https://kotlov.by/city/select/gomel?return=%2Fcontacts')
            ->assertRedirect('/contacts')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertPlainCookie('selected_city', 'gomel');

        $response = $this->withCookie('selected_city', 'gomel')
            ->get('https://kotlov.by/contacts');

        $response->assertOk();
        $response->assertSee('г. Гомель');
        $response->assertSee('ООО «Гомель Тепло»');
        $response->assertSee('+375 (29) 111-22-33');
        $response->assertSee('gomel@example.by');
        $response->assertSee('Беларусь, г. Гомель, ул. Тестовая, 1');
    }

    public function test_disabled_city_cannot_be_selected(): void
    {
        $this->city('grodno', 'Гродно', false);

        $this->get('https://kotlov.by/city/select/grodno')->assertNotFound();
    }

    public function test_external_return_url_is_rejected(): void
    {
        $this->get('https://kotlov.by/city/select/minsk?return=https%3A%2F%2Fevil.example')
            ->assertRedirect('/')
            ->assertPlainCookie('selected_city', 'minsk');
    }

    public function test_unknown_or_disabled_cookie_falls_back_to_minsk_contacts(): void
    {
        $this->city('grodno', 'Гродно', false);

        $response = $this->withCookie('selected_city', 'grodno')
            ->get('https://kotlov.by/contacts');

        $response->assertOk();
        $response->assertSee('г. Минск');
        $response->assertSee('+375 (29) 377-70-92');
    }

    private function city(string $slug, string $name, bool $storefrontEnabled): City
    {
        return City::query()->create([
            'slug' => $slug,
            'name' => $name,
            'name_in' => $name === 'Минск' ? 'в Минске' : 'в '.$name,
            'name_title' => $name,
            'region' => $name === 'Минск' ? 'Минск' : null,
            'is_active' => true,
            'storefront_enabled' => $storefrontEnabled,
        ]);
    }
}
