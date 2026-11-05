<?php

namespace Tests\Feature;

use App\Filament\Resources\CityStorefronts\CityStorefrontResource;
use App\Filament\Resources\CityStorefronts\Pages\EditCityStorefront;
use App\Filament\Resources\CityStorefronts\Pages\ListCityStorefronts;
use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use App\Services\CityStorefrontManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Livewire\Livewire;

class CityStorefrontManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_publish_city_without_a_public_branch(): void
    {
        $city = $this->city();
        $this->expectException(ValidationException::class);
        app(CityStorefrontManager::class)->update($city, ['storefront_enabled' => true]);
    }

    public function test_primary_branch_controls_storefront_and_contact_order_and_can_be_unpublished(): void
    {
        $city = $this->city();
        $first = $this->branch($city);
        $second = $this->branch($city, ['sort_order' => 500]);
        $city = app(CityStorefrontManager::class)->update($city, ['storefront_enabled' => true, 'primary_partner_branch_id' => $second->id]);
        $this->assertTrue($city->storefront_enabled);
        $this->assertSame($second->id, PartnerBranch::query()->visibleIn($city)->first()->id);
        $this->assertSame('https://lida.kotlov.by', $city->storefrontUrl());
        $city = app(CityStorefrontManager::class)->update($city, ['storefront_enabled' => false, 'primary_partner_branch_id' => $second->id]);
        $this->assertFalse($city->storefront_enabled);
        $this->assertTrue($first->fresh()->is_active);
    }

    public function test_other_city_branch_cannot_be_assigned(): void
    {
        $city = $this->city();
        $other = $this->branch($this->city('borisov'));
        $this->expectException(ValidationException::class);
        app(CityStorefrontManager::class)->update($city, ['storefront_enabled' => true, 'primary_partner_branch_id' => $other->id]);
    }

    public function test_regular_manager_cannot_manage_city_sites(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'can_manage_b2b_orders' => false]));
        $this->assertFalse(CityStorefrontResource::canViewAny());
        $this->assertFalse(CityStorefrontResource::canEdit($this->city()));
    }

    public function test_admin_can_open_city_management_and_publish_selected_branch(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $city = $this->city();
        $branch = $this->branch($city);
        Livewire::test(ListCityStorefronts::class)->assertSuccessful()->assertCanSeeTableRecords([$city]);
        Livewire::test(EditCityStorefront::class, ['record' => $city->id])
            ->assertSuccessful()->fillForm(['primary_partner_branch_id' => $branch->id, 'storefront_enabled' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertTrue($city->fresh()->storefront_enabled);
    }

    private function city(string $slug = 'lida'): City
    {
        return City::create(['slug' => $slug, 'name' => $slug, 'name_in' => $slug, 'name_title' => $slug, 'is_active' => true, 'storefront_enabled' => false]);
    }

    private function branch(City $city, array $data = []): PartnerBranch
    {
        $user = User::factory()->create();
        $client = B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Партнёр']);

        return PartnerBranch::create(array_merge(['city_id' => $city->id, 'b2b_client_id' => $client->id, 'name' => 'Магазин', 'address' => 'Адрес магазина', 'phones' => ['+375290000000'], 'is_active' => true, 'sort_order' => 100], $data));
    }
}
