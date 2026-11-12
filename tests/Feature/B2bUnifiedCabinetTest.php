<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\InstallerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bUnifiedCabinetTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_logs_in_to_b2b_cabinet_and_edits_own_page_there(): void
    {
        [$installer, $client] = $this->partner('installer', 'secret-pass-1');
        // The page already exists: it is created when an installer partner is approved.
        $profile = InstallerProfile::query()->where('user_id', $installer->id)->sole();
        $profile->update([
            'company_name' => 'НС Трейд', 'contact_name' => 'Павел',
            'phone' => '+375296769990', 'slug' => 'ns-trade', 'status' => 'active', 'is_published' => true,
        ]);

        $this->post(route('login.submit'), ['email' => $installer->email, 'password' => 'secret-pass-1'])
            ->assertRedirect(route('b2b.cabinet'));

        $this->get(route('account.installer-profile'))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'page']));
        $this->get(route('b2b.cabinet', ['section' => 'page']))
            ->assertOk()
            ->assertSee('Моя страница монтажника')
            ->assertSee('Данные профиля')
            ->assertSee('Открыть страницу в каталоге');

        $this->from(route('b2b.cabinet', ['section' => 'page']))
            ->put(route('account.installer-profile.update'), [
                'contact_name' => 'Павел Иванов', 'phone' => '+375296769990', 'city' => 'Борисов',
                'short_description' => 'Котельные под ключ',
            ])
            ->assertRedirect(route('b2b.cabinet', ['section' => 'page']));
        $this->assertSame('Павел Иванов', $profile->fresh()->contact_name);
        $this->assertSame('Котельные под ключ', $profile->fresh()->short_description);
    }

    public function test_installer_can_recreate_a_missing_page_that_waits_for_moderation(): void
    {
        [$owner, $client] = $this->partner('installer');
        InstallerProfile::query()->where('user_id', $owner->id)->delete();

        $this->actingAs($owner)->get(route('b2b.cabinet', ['section' => 'page']))
            ->assertOk()->assertSee('Моя страница монтажника')->assertSee('Создать страницу монтажника');

        $this->actingAs($owner)->post(route('b2b.cabinet.page.create'))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'page']));
        $this->actingAs($owner)->post(route('b2b.cabinet.page.create'));

        $profile = InstallerProfile::query()->sole();
        $this->assertSame($owner->id, $profile->user_id);
        $this->assertSame('ООО «Тепло»', $profile->company_name);
        $this->assertSame('pending', $profile->status);
        $this->assertFalse($profile->is_published);
        $this->assertSame('installer', $client->fresh()->partner_type);

        $this->actingAs($owner)->get(route('b2b.cabinet', ['section' => 'page']))
            ->assertOk()->assertSee('Страница на проверке KOTLOV')->assertSee('Данные профиля');
    }

    public function test_only_company_profile_managers_edit_the_page(): void
    {
        [$owner, $client] = $this->partner('installer');
        $profile = InstallerProfile::query()->where('user_id', $owner->id)->sole();
        $profile->update(['contact_name' => 'Анна', 'status' => 'active', 'is_published' => true]);
        $viewer = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        $client->members()->create(['user_id' => $viewer->id, 'role' => 'viewer', 'is_active' => true, 'joined_at' => now()]);

        $this->actingAs($viewer)->put(route('account.installer-profile.update'), [
            'contact_name' => 'Чужой', 'phone' => '+375290000000',
        ])->assertNotFound();
        $this->assertSame('Анна', $profile->fresh()->contact_name);
        $this->actingAs($viewer)->post(route('b2b.cabinet.page.create'))->assertForbidden();
    }

    /** @return array{User, B2bClient} */
    public function test_approved_installer_gets_a_page_to_fill_and_sees_it_on_the_desk(): void
    {
        $user = User::factory()->create(['client_type' => 'installer', 'is_active' => true]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id, 'status' => 'pending', 'partner_type' => 'installer',
            'company_name' => 'ООО «Монтаж Плюс»', 'unp' => '191234567', 'contact_name' => 'Олег',
            'contact_email' => $user->email, 'contact_phone' => '+375292222222',
        ]);
        $this->assertSame(0, InstallerProfile::query()->count(), 'No page while the application is pending.');

        app(\App\Services\B2bClientWorkflow::class)->approve($client);
        $profile = InstallerProfile::query()->sole();
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame('ООО «Монтаж Плюс»', $profile->company_name);
        $this->assertSame('191234567', $profile->unp);
        $this->assertSame('pending', $profile->status);
        $this->assertFalse((bool) $profile->is_published);

        $this->actingAs($user)->get(route('b2b.cabinet'))
            ->assertOk()->assertSee('Страница монтажника')->assertSee('Заполнить страницу');
        $this->actingAs($user)->get(route('b2b.cabinet', ['section' => 'page']))
            ->assertOk()->assertSee('Данные профиля')->assertDontSee('Создать страницу монтажника');

        // Saving the client again never creates a second page.
        $client->fresh()->update(['contact_name' => 'Олег Петров']);
        $this->assertSame(1, InstallerProfile::query()->count());
    }

    public function test_wholesale_client_switched_to_installer_by_manager_gets_a_page(): void
    {
        [$owner, $client] = $this->partner('wholesale');
        $this->assertSame(0, InstallerProfile::query()->count());

        $client->update(['partner_type' => 'installer']);
        $this->assertSame($owner->id, InstallerProfile::query()->sole()->user_id);
    }

    private function partner(string $type, string $password = 'password-123'): array
    {
        $user = User::factory()->create([
            'client_type' => $type === 'installer' ? 'installer' : 'wholesale',
            'role' => $type === 'installer' ? 'installer' : 'client',
            'b2b_approved' => true, 'is_active' => true, 'password' => $password,
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id, 'status' => 'approved', 'partner_type' => $type,
            'company_name' => 'ООО «Тепло»', 'contact_name' => $user->name, 'contact_email' => $user->email,
            'contact_phone' => '+375291111111',
        ]);

        return [$user, $client];
    }
}
