<?php

namespace Tests\Feature;

use App\Filament\Resources\InstallerProfiles\Pages\ListInstallerProfiles;
use App\Mail\InstallerB2bInvitationMail;
use App\Models\B2bClient;
use App\Models\InstallerProfile;
use App\Models\User;
use App\Services\InstallerB2bConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InstallerB2bConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_connects_installer_to_b2b_and_installer_sets_password_once(): void
    {
        Mail::fake();
        $installer = User::factory()->create(['email' => 'pavel@ns-trade.test', 'role' => 'installer', 'email_verified_at' => null]);
        $profile = $this->profile($installer, ['company_name' => 'НС Трейд', 'unp' => '691234567', 'contact_name' => 'Павел', 'phone' => '+375296769990']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin);
        Livewire::test(ListInstallerProfiles::class)
            ->assertSee('Не подключён')
            ->callTableAction('connectB2b', $profile)
            ->assertHasNoTableActionErrors();

        $client = B2bClient::query()->where('user_id', $installer->id)->sole();
        $this->assertSame('approved', $client->status);
        $this->assertSame('installer', $client->partner_type);
        $this->assertSame('НС Трейд', $client->company_name);
        $this->assertSame('691234567', $client->unp);
        $this->assertSame('+375296769990', $client->contact_phone);
        $this->assertTrue($installer->fresh()->b2b_approved);
        $this->assertNotNull($profile->fresh()->b2b_invited_at);
        $this->assertDatabaseHas('b2b_client_audit_logs', ['b2b_client_id' => $client->id, 'event' => 'installer_converted']);

        $url = null;
        Mail::assertSent(InstallerB2bInvitationMail::class, function (InstallerB2bInvitationMail $mail) use (&$url): bool {
            $url = $mail->activationUrl;

            return $mail->hasTo('pavel@ns-trade.test');
        });
        $this->assertStringContainsString('НС Трейд', (new InstallerB2bInvitationMail($profile->fresh(), $url))->render());

        // The installer opens the letter, sets a password and lands in the B2B cabinet.
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Задайте пароль')->assertSee('НС Трейд');
        $this->post($url, ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertRedirect(route('b2b.cabinet'));
        $this->assertAuthenticatedAs($installer->fresh());
        $this->assertNotNull($installer->fresh()->email_verified_at);
        $this->get(route('b2b.cabinet'))->assertOk()->assertSee('Моя страница монтажника');

        // The same link does not work twice.
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Пароль уже задан');
        $this->post($url, ['password' => 'other-secret-2', 'password_confirmation' => 'other-secret-2'])->assertForbidden();
    }

    public function test_placeholder_account_email_is_replaced_by_profile_email_and_missing_email_is_reported(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'installer.application.7@profiles.kotlov.by', 'role' => 'installer']);
        $profile = $this->profile($user, ['email' => 'mister@mail.test', 'contact_name' => 'Леонид']);

        app(InstallerB2bConversion::class)->convert($profile);
        $this->assertSame('mister@mail.test', $user->fresh()->email);
        Mail::assertSent(InstallerB2bInvitationMail::class, fn ($mail): bool => $mail->hasTo('mister@mail.test'));

        $silent = User::factory()->create(['email' => 'installer.application.8@profiles.kotlov.by', 'role' => 'installer']);
        $noEmail = $this->profile($silent, ['contact_name' => 'Виталий']);
        $this->assertSame('no_email', app(InstallerB2bConversion::class)->state($noEmail));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InstallerB2bConversion::class)->convert($noEmail);
    }

    public function test_paused_b2b_client_is_not_reactivated_by_conversion(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'installer']);
        $profile = $this->profile($user, ['contact_name' => 'Алексей']);
        B2bClient::query()->create(['user_id' => $user->id, 'status' => 'paused', 'partner_type' => 'installer', 'company_name' => 'Отопление плюс']);

        try {
            app(InstallerB2bConversion::class)->convert($profile);
            $this->fail('Приостановленный клиент не должен включаться автоматически.');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->assertSame('paused', B2bClient::query()->sole()->status);
        Mail::assertNothingSent();
    }

    /** @param array<string, mixed> $data */
    private function profile(User $user, array $data): InstallerProfile
    {
        return InstallerProfile::query()->create([
            'user_id' => $user->id,
            'slug' => 'installer-'.$user->id,
            'status' => 'active',
            'is_published' => true,
            'city' => 'Минск',
        ] + $data);
    }
}
