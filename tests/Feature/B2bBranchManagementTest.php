<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use App\Services\PartnerBranchModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Resources\PartnerBranches\Pages\ListPartnerBranches;

class B2bBranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_wholesale_cabinet_shows_shop_management_and_cannot_create_installer_page(): void
    {
        [$user, $client] = $this->partner();
        $this->actingAs($user)->get(route('b2b.cabinet', ['section' => 'page']))->assertOk()
            ->assertSee('Мои магазины и филиалы')->assertSee('Добавить магазин')->assertDontSee('Создать страницу монтажника');
        $this->post(route('b2b.cabinet.page.create'))->assertForbidden();
        $this->assertSame('wholesale', $client->fresh()->partner_type);
    }

    public function test_new_store_is_private_until_approved_and_preview_is_not_indexable(): void
    {
        [$user, $client] = $this->partner();
        $this->actingAs($user)->post(route('b2b.branches.store'), $this->data())->assertRedirect();
        $branch = $client->branches()->sole();
        $this->assertFalse($branch->is_active);
        $this->assertNotNull($branch->changes_submitted_at);
        $this->assertSame('Лидский магазин', $branch->pending_changes['name']);
        $this->get(route('b2b.branches.preview', $branch))->assertOk()->assertSee('Лидский магазин')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $admin = User::factory()->create(['role' => 'admin']);
        app(PartnerBranchModeration::class)->approve($branch, $admin);
        $this->assertTrue($branch->fresh()->is_active);
        $this->assertNull($branch->fresh()->pending_changes);
        $this->assertSame('Лидский магазин', $branch->fresh()->name);
        $this->assertFalse($branch->city->storefront_enabled);
    }

    public function test_existing_public_contact_stays_unchanged_until_approval(): void
    {
        [$user, $client] = $this->partner();
        $branch = $client->branches()->create(['name' => 'Действующий магазин', 'city_id' => $this->data()['city_id'], 'address' => 'Старый адрес', 'phones' => ['+375290000000'], 'is_active' => true]);
        $this->actingAs($user)->post(route('b2b.branches.update', $branch), $this->data())->assertRedirect();
        $this->assertSame('Старый адрес', $branch->fresh()->address);
        $this->assertSame('ул. Новая, 1', $branch->fresh()->pending_changes['address']);
    }

    public function test_partner_cannot_edit_or_preview_another_company_store(): void
    {
        [$owner, $client] = $this->partner();
        $branch = $client->branches()->create(['name' => 'Чужой магазин', 'city_id' => $this->data()['city_id'], 'phones' => [], 'is_active' => false]);
        [$other] = $this->partner();
        $this->actingAs($other)->post(route('b2b.branches.update', $branch), $this->data())->assertForbidden();
        $this->get(route('b2b.branches.preview', $branch))->assertForbidden();
    }

    public function test_admin_cabinet_preview_shows_shop_form_without_allowing_submission(): void
    {
        [, $client] = $this->partner();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.b2b-clients.cabinet-preview', ['client' => $client, 'section' => 'page']))
            ->assertOk()->assertSee('Мои магазины и филиалы')->assertSee('fieldset disabled', false);
        $this->post(route('b2b.branches.store'), $this->data())->assertForbidden();
    }

    public function test_manager_can_approve_the_submitted_shop_from_admin_list(): void
    {
        [$user, $client] = $this->partner();
        $data = $this->data();
        $data['phones'] = [$data['phone']];
        $branch = app(PartnerBranchModeration::class)->submit($client, $user, $data);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ListPartnerBranches::class)->assertSuccessful()->assertCanSeeTableRecords([$branch])
            ->callTableAction('approveChanges', $branch)->assertHasNoErrors();
        $this->assertTrue($branch->fresh()->is_active);
        $this->assertNull($branch->fresh()->changes_submitted_at);
    }

    private function partner(): array
    {
        $user = User::factory()->create(['role' => 'client', 'client_type' => 'wholesale', 'b2b_approved' => true]);
        $client = B2bClient::create(['user_id' => $user->id, 'partner_type' => 'wholesale', 'status' => 'approved', 'company_name' => 'Магазин']);
        return [$user, $client];
    }

    public function test_store_requires_hours_and_preserves_multiple_phone_numbers(): void
    {
        [$user, $client] = $this->partner();
        $data = $this->data();
        unset($data['working_hours']);
        $this->actingAs($user)->post(route('b2b.branches.store'), $data)->assertSessionHasErrors('working_hours');
        $this->assertSame(0, $client->branches()->count());
        $data['working_hours'] = 'Пн–Сб 9:00–18:00, Вс — выходной';
        $data['phones'] = ['+375291234567', '+375331234567', ''];
        $this->post(route('b2b.branches.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['+375291234567', '+375331234567'], $client->branches()->sole()->pending_changes['phones']);
    }

    private function data(): array
    {
        $city = City::firstOrCreate(['slug' => 'lida'], ['name' => 'Лида', 'name_in' => 'в Лиде', 'name_title' => 'Лиде', 'is_active' => true, 'storefront_enabled' => false]);
        return ['city_id' => $city->id, 'name' => 'Лидский магазин', 'address' => 'ул. Новая, 1', 'phone' => '+375291234567', 'working_hours' => 'Пн–Пт 9:00–18:00'];
    }
}
