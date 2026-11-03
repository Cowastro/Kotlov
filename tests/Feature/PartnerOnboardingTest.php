<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmCounterparties\Pages\ListCrmCounterparties;
use App\Filament\Resources\PartnerOnboardings\Pages\ListPartnerOnboardings;
use App\Mail\PartnerOnboardingApprovedMail;
use App\Mail\PartnerOnboardingInviteMail;
use App\Models\B2bClient;
use App\Models\City;
use App\Models\CrmCounterparty;
use App\Models\PartnerBranch;
use App\Models\PartnerOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_is_invited_fills_the_questionnaire_and_is_connected_with_points(): void
    {
        Mail::fake();
        $minsk = $this->city('minsk', 'Минск');
        $borisov = $this->city('borisov', 'Борисов');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin);
        Livewire::test(ListPartnerOnboardings::class)
            ->callAction('invite', data: ['email' => 'Shop@Teplo.by', 'company_name' => 'ООО «Тепло»', 'unp' => '191234567'])
            ->assertHasNoActionErrors();
        $onboarding = PartnerOnboarding::query()->sole();
        $this->assertSame('shop@teplo.by', $onboarding->email);
        Mail::assertQueued(PartnerOnboardingInviteMail::class, fn ($mail): bool => $mail->hasTo('shop@teplo.by'));
        $this->assertStringContainsString('Заполнить анкету партнёра', (new PartnerOnboardingInviteMail($onboarding))->render());

        auth()->logout();
        $this->get($onboarding->url())
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Анкета партнёра')->assertSee('value="ООО «Тепло»"', false);
        $this->assertSame('opened', $onboarding->fresh()->status);

        $this->post(route('partner-onboarding.submit', $onboarding->token), $this->answers([]))
            ->assertSessionHasErrors('points');
        $this->post(route('partner-onboarding.submit', $onboarding->token), $this->answers([
            ['city_id' => $minsk->id, 'name' => '', 'address' => 'г. Минск, ул. Тестовая, 1', 'phones' => '+375 (29) 111-11-11, +375 (33) 222-22-22', 'working_hours' => 'Пн–Пт 9–18'],
            ['city_id' => $borisov->id, 'name' => 'Магазин «Тепло» Борисов', 'address' => 'г. Борисов, ул. Демина, 5', 'phones' => '+375 (29) 333-33-33', 'working_hours' => ''],
        ]))->assertRedirect($onboarding->url());
        $onboarding->refresh();
        $this->assertSame('submitted', $onboarding->status);
        $this->assertSame(['+375 (29) 111-11-11', '+375 (33) 222-22-22'], $onboarding->data['points'][0]['phones']);
        $this->assertTrue($admin->notifications()->get()->contains(fn ($n) => ($n->data['title'] ?? '') === 'Анкета партнёра заполнена'));

        $this->actingAs($admin);
        Livewire::test(ListPartnerOnboardings::class)
            ->assertSee('ООО «Тепло»')->assertSee('Минск, Борисов')
            ->callTableAction('approve', $onboarding)
            ->assertHasNoTableActionErrors();

        $client = B2bClient::query()->sole();
        $this->assertSame('approved', $client->status);
        $this->assertSame('wholesale', $client->partner_type);
        $this->assertSame('191234567', $client->unp);
        $this->assertSame('shop@teplo.by', $client->user->email);
        $this->assertSame(['ООО «Тепло»', 'Магазин «Тепло» Борисов'], PartnerBranch::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame('approved', $onboarding->fresh()->status);
        Mail::assertQueued(PartnerOnboardingApprovedMail::class, fn ($mail): bool => $mail->hasTo('shop@teplo.by')
            && $mail->setPassword && str_contains($mail->loginUrl, '/b2b/activate/'));

        // The link is closed after approval.
        $this->post(route('partner-onboarding.submit', $onboarding->token), $this->answers([]))->assertStatus(410);
    }

    public function test_installer_without_points_gets_an_installer_page_and_crm_bulk_invite_skips_contacts_without_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $withEmail = CrmCounterparty::query()->create(['type' => array_key_first(CrmCounterparty::TYPES), 'status' => 'active', 'name' => 'Монтаж Плюс', 'unp' => '192222222']);
        $withEmail->contactPoints()->create(['type' => 'email', 'value' => 'montazh@plus.by', 'is_primary' => true, 'is_active' => true]);
        $noEmail = CrmCounterparty::query()->create(['type' => array_key_first(CrmCounterparty::TYPES), 'status' => 'active', 'name' => 'Без почты']);

        $this->actingAs($admin);
        Livewire::test(ListCrmCounterparties::class)
            ->callTableBulkAction('invitePartners', [$withEmail, $noEmail])
            ->assertHasNoTableBulkActionErrors();
        $onboarding = PartnerOnboarding::query()->sole();
        $this->assertSame('montazh@plus.by', $onboarding->email);
        $this->assertSame($withEmail->id, $onboarding->crm_counterparty_id);

        $this->post(route('partner-onboarding.submit', $onboarding->token), array_merge($this->answers([]), [
            'partner_type' => 'installer', 'company_name' => 'ООО «Монтаж Плюс»', 'unp' => '192222222', 'email' => 'montazh@plus.by',
        ]))->assertRedirect();
        app(\App\Services\PartnerOnboardingService::class)->approve($onboarding->fresh(), $admin);

        $client = B2bClient::query()->sole();
        $this->assertSame('installer', $client->partner_type);
        $this->assertSame($withEmail->id, $client->counterparty_id);
        $this->assertSame(1, \App\Models\InstallerProfile::query()->where('user_id', $client->user_id)->count());
    }

    private function answers(array $points): array
    {
        return [
            'company_name' => 'ООО «Тепло»', 'unp' => '191234567', 'legal_address' => 'г. Минск, ул. Юридическая, 1',
            'partner_type' => 'wholesale', 'contact_name' => 'Анна', 'phone' => '+375 (29) 000-00-00',
            'email' => 'shop@teplo.by', 'website' => 'teplo.by', 'description' => 'Котлы и печи', 'points' => $points,
        ];
    }

    private function city(string $slug, string $name): City
    {
        return City::query()->create([
            'slug' => $slug, 'name' => $name, 'name_in' => 'в '.$name, 'name_title' => $name,
            'region' => $name, 'is_active' => true, 'storefront_enabled' => true,
        ]);
    }
}
