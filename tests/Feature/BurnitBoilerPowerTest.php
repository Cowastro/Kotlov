<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\BoilerSpecifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BurnitBoilerPowerTest extends TestCase
{
    use RefreshDatabase;

    public function test_burnit_boilers_get_their_model_power_as_a_spec(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Пеллетные котлы', 'parent_id' => 0, 'is_active' => true]);
        $boiler = Product::query()->create(['name' => 'Пеллетный котел BURNIT VIZION 4G/18', 'slug' => 'pelletnyi-kotel-burnit-vizion-4g18', 'category_id' => $category->id, 'price' => 100, 'is_active' => true]);
        $other = Product::query()->create(['name' => 'Пеллетный котел BURNIT Advant 4G 25', 'slug' => 'pelletnyi-kotel-burnit-advant-4g-25', 'category_id' => $category->id, 'price' => 100, 'is_active' => true, 'specs' => [['key' => 'Мощность', 'value' => '24 кВт']]]);

        (require database_path('migrations/2026_11_29_240000_add_burnit_boiler_power.php'))->up();

        $this->assertSame(18.0, (float) app(BoilerSpecifications::class)->numericFact($boiler->fresh()->load('allAttributeValues'), 'power')['value']);
        $this->assertSame([['key' => 'Мощность', 'value' => '24 кВт']], $other->fresh()->specs);
    }

    public function test_a_burn_time_at_full_power_is_not_a_power_spec(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Пеллетные котлы', 'parent_id' => 0, 'is_active' => true]);
        $boiler = Product::query()->create(['name' => 'Пеллетный котел BURNIT Advant 4G 13', 'slug' => 'pelletnyi-kotel-burnit-advant-4g-13', 'category_id' => $category->id, 'price' => 100, 'is_active' => true, 'specs' => [['key' => 'Время горения на полной мощности', 'value' => '8 часов']]]);

        (require database_path('migrations/2026_11_29_250000_add_burnit_boiler_power_remaining.php'))->up();

        $this->assertSame(13.0, (float) app(BoilerSpecifications::class)->numericFact($boiler->fresh()->load('allAttributeValues'), 'power')['value']);
    }
}
