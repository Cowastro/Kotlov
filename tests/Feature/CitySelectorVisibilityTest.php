<?php

namespace Tests\Feature;

use App\Models\City;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CitySelectorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_active_city_is_shown_as_static_header_label_without_selector(): void
    {
        City::query()->update(['is_active' => false, 'storefront_enabled' => false]);
        City::query()->where('slug', 'minsk')->update(['is_active' => true]);

        $state = $this->citySelectorState();

        $this->assertFalse($state['enabled']);
        $this->assertSame(1, array_sum(array_map('count', $state['regions'])));

        $header = view('partials.amerce-header', [
            'navCategories' => collect(),
            'navBrands' => collect(),
            'citySelectorEnabled' => false,
            'cityName' => 'Минск',
        ])->render();

        $this->assertStringContainsString('data-city-selector="static"', $header);
        $this->assertStringContainsString('г. Минск', $header);
        $this->assertStringNotContainsString('href="#citySelector"', $header);

        $selector = view('partials.city-selector', [
            'citySelectorEnabled' => false,
            'citySelectorRegions' => $state['regions'],
            'cityName' => 'Минск',
        ])->render();

        $this->assertStringNotContainsString('id="citySelector"', $selector);
    }

    public function test_selector_uses_active_cities_even_without_dedicated_storefronts(): void
    {
        City::query()->update(['is_active' => false, 'storefront_enabled' => false]);
        City::query()->where('slug', 'minsk')->update(['is_active' => true]);
        City::query()->updateOrCreate(['slug' => 'gomel'], [
            'name' => 'Гомель',
            'name_in' => 'в Гомеле',
            'name_title' => 'Гомеле',
            'region' => 'Гомельская',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);
        City::query()->updateOrCreate(['slug' => 'grodno'], [
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'region' => 'Гродненская',
            'is_active' => false,
            'storefront_enabled' => false,
        ]);

        $state = $this->citySelectorState();

        $this->assertTrue($state['enabled']);
        $this->assertSame(['Минская', 'Гомельская'], array_keys($state['regions']));

        $selector = view('partials.city-selector', [
            'citySelectorEnabled' => true,
            'citySelectorRegions' => $state['regions'],
            'cityName' => 'Минск',
        ])->render();

        $this->assertStringContainsString('id="citySelector"', $selector);
        $this->assertStringContainsString('Минск', $selector);
        $this->assertStringContainsString('Гомель', $selector);
        $this->assertStringNotContainsString('Гродно', $selector);
        $this->assertStringNotContainsString('Брестская', $selector);

        $header = view('partials.amerce-header', [
            'navCategories' => collect(),
            'navBrands' => collect(),
            'citySelectorEnabled' => true,
            'cityName' => 'Минск',
        ])->render();

        $this->assertStringContainsString('href="#citySelector"', $header);
        $this->assertStringContainsString('г. Минск', $header);
    }

    /** @return array{regions: array<string, array<int, array<string, string>>>, enabled: bool} */
    private function citySelectorState(): array
    {
        $method = new ReflectionMethod(AppServiceProvider::class, 'getCitySelectorState');

        return $method->invoke(new AppServiceProvider(app()));
    }
}
