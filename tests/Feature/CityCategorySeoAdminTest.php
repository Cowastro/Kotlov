<?php

namespace Tests\Feature;

use App\Filament\Resources\CityCategorySeos\Pages\CreateCityCategorySeo;
use App\Filament\Resources\CityCategorySeos\Pages\ListCityCategorySeos;
use App\Models\Category;
use App\Models\City;
use App\Models\CityCategorySeo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CityCategorySeoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_writes_local_seo_for_a_city_category(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel('admin');
        $city = City::query()->create(['slug' => 'borisov', 'name' => 'Борисов', 'name_in' => 'в Борисове', 'name_title' => 'Борисове', 'is_active' => true, 'storefront_enabled' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'pechki'], ['name' => 'Печи', 'is_active' => true]);

        Livewire::test(ListCityCategorySeos::class)->assertSuccessful();
        Livewire::test(CreateCityCategorySeo::class)
            ->fillForm(['city_id' => $city->id, 'category_id' => $category->id, 'h1' => 'Печи в Борисове', 'meta_title' => 'Печи в Борисове | KOTLOV', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame('Печи в Борисове', CityCategorySeo::query()->where('city_id', $city->id)->value('h1'));

        Livewire::test(CreateCityCategorySeo::class)
            ->fillForm(['city_id' => $city->id, 'category_id' => $category->id, 'h1' => 'Дубль'])
            ->call('create')->assertHasFormErrors(['category_id' => 'unique']);
    }
}
