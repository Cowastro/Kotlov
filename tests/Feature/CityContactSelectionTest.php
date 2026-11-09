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
            ->assertPlainCookie('selected_city', 'gomel')
            ->assertPlainCookie('selected_city_source', 'manual');

        $response = $this->withUnencryptedCookie('selected_city', 'gomel')
            ->get('https://kotlov.by/contacts');

        $response->assertOk();
        $response->assertSee('г. Гомель');
        $response->assertSee('ООО «Гомель Тепло»');
        $response->assertSee('+375 (29) 111-22-33');
        $response->assertSee('gomel@example.by');
        $response->assertSee('Беларусь, г. Гомель, ул. Тестовая, 1');
    }

    public function test_city_selector_posts_without_exposing_page_specific_crawl_urls(): void
    {
        $this->city('gomel', 'Гомель', false);

        $html = view('partials.city-selector', [
            'citySelectorEnabled' => true,
            'citySelectorRegions' => [
                'Минская' => [
                    ['slug' => 'minsk', 'name' => 'Минск'],
                    ['slug' => 'gomel', 'name' => 'Гомель'],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('/city/select"', $html);
        $this->assertStringContainsString('name="slug" value="gomel"', $html);
        $this->assertStringNotContainsString('href="/city/select/', $html);

        $this->post('https://kotlov.by/city/select', [
            'slug' => 'gomel',
            'return' => '/contacts',
        ])->assertRedirect('/contacts')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertPlainCookie('selected_city', 'gomel');
    }

    public function test_active_city_without_a_dedicated_storefront_can_be_selected(): void
    {
        $this->city('vitebsk', 'Витебск', false);

        $this->get('https://kotlov.by/city/select/vitebsk?return=%2F')
            ->assertRedirect('/')
            ->assertPlainCookie('selected_city', 'vitebsk');

        $this->withUnencryptedCookie('selected_city', 'vitebsk')
            ->get('https://kotlov.by/')
            ->assertOk()
            ->assertSee('г. Витебск');
    }

    public function test_cloudflare_location_header_detects_vitebsk_and_remembers_it(): void
    {
        $this->city('vitebsk', 'Витебск', false);

        $this->withHeaders([
            'CF-IPCountry' => 'BY',
            'CF-IPCity' => 'Vitebsk',
        ])->get('https://kotlov.by/')
            ->assertOk()
            ->assertSee('г. Витебск')
            ->assertPlainCookie('selected_city', 'vitebsk')
            ->assertPlainCookie('selected_city_source', 'auto');
    }

    public function test_manual_city_selection_is_never_overwritten_by_ip_location(): void
    {
        $this->city('gomel', 'Гомель', false);
        $this->city('vitebsk', 'Витебск', false);

        $this->withUnencryptedCookie('selected_city', 'gomel')
            ->withUnencryptedCookie('selected_city_source', 'manual')
            ->withHeaders(['CF-IPCountry' => 'BY', 'CF-IPCity' => 'Vitebsk'])
            ->get('https://kotlov.by/')
            ->assertOk()
            ->assertSee('г. Гомель')
            ->assertCookieMissing('selected_city_source');
    }

    public function test_automatic_city_can_follow_a_changed_ip_location(): void
    {
        $this->city('gomel', 'Гомель', false);
        $this->city('vitebsk', 'Витебск', false);

        $this->withUnencryptedCookie('selected_city', 'gomel')
            ->withUnencryptedCookie('selected_city_source', 'auto')
            ->withHeaders(['CF-IPCountry' => 'BY', 'CF-IPCity' => 'Vitebsk'])
            ->get('https://kotlov.by/')
            ->assertOk()
            ->assertSee('г. Витебск')
            ->assertPlainCookie('selected_city', 'vitebsk')
            ->assertPlainCookie('selected_city_source', 'auto');
    }

    public function test_belarusian_ip_city_alias_is_mapped_to_the_catalog_city(): void
    {
        $this->city('borisov', 'Борисов', false);

        $this->withHeaders(['CF-IPCountry' => 'BY', 'CF-IPCity' => 'Barysaw'])
            ->get('https://kotlov.by/')
            ->assertOk()
            ->assertSee('г. Борисов')
            ->assertPlainCookie('selected_city', 'borisov');
    }

    public function test_inactive_city_cannot_be_selected(): void
    {
        $city = $this->city('grodno', 'Гродно', false);
        $city->update(['is_active' => false]);

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
        $city = $this->city('grodno', 'Гродно', false);
        $city->update(['is_active' => false]);

        $response = $this->withUnencryptedCookie('selected_city', 'grodno')
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
