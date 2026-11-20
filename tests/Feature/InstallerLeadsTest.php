<?php

namespace Tests\Feature;

use App\Mail\InstallerLeadMail;
use App\Models\InstallerProfile;
use App\Models\InstallRequest;
use App\Models\InstallRequestLead;
use App\Models\User;
use App\Services\InstallerLeads\InstallerLeadDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InstallerLeadsTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => null, 'installer_leads.send_to_all' => false]);
    }

    public function test_a_new_request_is_offered_to_installers_of_its_region_and_kind_once(): void
    {
        Mail::fake();
        $grodno = $this->profile(['region' => 'Гродненская область', 'specializations' => ['pellet', 'heating'], 'email' => 'grodno@example.com']);
        $nationwide = $this->profile(['nationwide' => true, 'specializations' => [], 'email' => 'all@example.com']);
        $this->profile(['region' => 'Брестская область', 'specializations' => ['pellet'], 'email' => 'brest@example.com']);
        $this->profile(['region' => 'Гродненская область', 'specializations' => ['fireplace'], 'email' => 'fire@example.com']);
        $this->profile(['region' => 'Гродненская область', 'email' => 'off@example.com', 'lead_notifications' => false]);
        $this->profile(['region' => 'Гродненская область', 'email' => 'hidden@example.com', 'is_published' => false]);
        $request = $this->request(['region' => 'Гродненская область', 'city' => 'Лида', 'specialization' => 'pellet']);
        $this->request(['region' => 'Гродненская область', 'specialization' => 'pellet', 'source' => 'pellet_burner_promo']);

        $this->artisan('installers:send-leads')->assertSuccessful();
        $this->artisan('installers:send-leads')->assertSuccessful();

        Mail::assertSent(InstallerLeadMail::class, 2);
        Mail::assertSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('grodno@example.com'));
        Mail::assertSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('all@example.com'));
        $this->assertEqualsCanonicalizing([$grodno->id, $nationwide->id], InstallRequestLead::query()->where('install_request_id', $request->id)->pluck('installer_profile_id')->all());
        $this->assertNotNull($request->fresh()->leads_sent_at);
    }

    public function test_minsk_installers_get_requests_from_the_minsk_region_and_a_city_counts_without_a_region(): void
    {
        Mail::fake();
        $this->profile(['region' => 'Минск', 'email' => 'minsk@example.com']);
        $this->profile(['work_cities' => ['Лида'], 'email' => 'lida@example.com']);
        $this->request(['region' => 'Минская область', 'city' => 'Заславль']);
        $this->request(['region' => null, 'city' => 'г. Лида']);

        $this->artisan('installers:send-leads')->assertSuccessful();

        Mail::assertSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('minsk@example.com') && $mail->lead->installRequest->city === 'Заславль');
        Mail::assertSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('lida@example.com') && $mail->lead->installRequest->city === 'г. Лида');
        Mail::assertNotSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('lida@example.com') && $mail->lead->installRequest->city === 'Заславль');
    }

    public function test_the_first_three_installers_take_the_request_and_only_they_see_the_contacts(): void
    {
        Mail::fake();
        $request = $this->request(['region' => 'Гомельская область', 'description' => 'Котёл на 24 кВт, звоните +375 29 123-45-67']);
        foreach (range(1, 4) as $i) {
            $this->profile(['region' => 'Гомельская область', 'email' => "g{$i}@example.com"]);
        }
        $this->artisan('installers:send-leads')->assertSuccessful();
        $leads = InstallRequestLead::query()->where('install_request_id', $request->id)->orderBy('id')->get();
        $this->assertCount(4, $leads);
        $urls = $leads->map(fn (InstallRequestLead $lead): string => app(InstallerLeadDispatcher::class)->url($lead));
        $mail = (new InstallerLeadMail($leads[0]->load(['installRequest', 'installerProfile']), $urls[0]))->render();
        $this->assertStringContainsString('Посмотреть и взять заявку', $mail);
        $this->assertStringContainsString('Гомельская область', $mail);
        $this->assertStringNotContainsString('1112233', $mail);

        $this->get($urls[0])->assertOk()->assertSee('Взять заявку')->assertDontSee('Иван Клиент')
            ->assertDontSee('123-45-67')->assertSee('[скрыто]', false);
        $this->assertNotNull($leads[0]->fresh()->opened_at);

        foreach ([0, 1, 2] as $i) {
            $this->post($urls[$i], ['action' => 'take'])->assertRedirect($urls[$i]);
        }
        $this->post($urls[3], ['action' => 'take'])->assertRedirect($urls[3]);

        $this->get($urls[0])->assertOk()->assertSee('Иван Клиент')->assertSee('+375291112233')->assertSee('123-45-67');
        $this->get($urls[3])->assertOk()->assertDontSee('Иван Клиент')->assertSee('уже взяли');
        $this->assertSame(3, InstallRequestLead::query()->whereNotNull('taken_at')->count());
        $this->assertNull($leads[3]->fresh()->taken_at);
    }

    public function test_a_link_without_a_valid_signature_is_refused_and_an_installer_can_turn_the_mails_off(): void
    {
        Mail::fake();
        $profile = $this->profile(['region' => 'Минск', 'email' => 'm@example.com']);
        $this->request(['region' => 'Минск']);
        $this->artisan('installers:send-leads')->assertSuccessful();
        $lead = InstallRequestLead::query()->firstOrFail();

        $this->get('/install-leads/'.$lead->id)->assertForbidden();

        $url = app(InstallerLeadDispatcher::class)->url($lead);
        $this->post($url, ['action' => 'unsubscribe'])->assertRedirect($url);
        $this->assertFalse((bool) $profile->fresh()->lead_notifications);

        $this->request(['region' => 'Минск']);
        Mail::fake();
        $this->artisan('installers:send-leads')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_requests_from_before_the_mailing_or_addressed_to_one_installer_are_not_offered(): void
    {
        Mail::fake();
        $chosen = $this->profile(['region' => 'Минск', 'email' => 'chosen@example.com']);
        $this->profile(['region' => 'Минск', 'email' => 'other@example.com']);
        $this->request(['region' => 'Минск', 'installer_profile_id' => $chosen->id, 'source' => 'installer_profile']);
        $this->request(['region' => 'Минск', 'created_at' => now()->subDays(5)]);

        $this->artisan('installers:send-leads')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_while_installers_are_few_every_one_gets_every_request_and_an_old_one_can_be_sent_by_hand(): void
    {
        config(['installer_leads.send_to_all' => true]);
        Mail::fake();
        $this->profile(['region' => 'Брестская область', 'specializations' => ['sauna'], 'email' => 'brest@example.com']);
        $this->profile(['region' => 'Минск', 'email' => 'minsk@example.com']);
        $this->profile(['region' => 'Минск', 'email' => 'off@example.com', 'lead_notifications' => false]);
        $old = $this->request(['region' => 'Гомельская область', 'specialization' => 'pellet', 'source' => 'heat_pump_installation', 'leads_sent_at' => now()->subDay()]);

        $this->artisan('installers:send-leads')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('installers:send-leads', ['--send' => $old->id])->expectsOutputToContain('metric.installer_leads.preview_recipients=2')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('installers:send-leads', ['--send' => $old->id, '--apply' => true])->expectsOutputToContain(':sent=2:failed=0')->assertSuccessful();
        Mail::assertSent(InstallerLeadMail::class, 2);
        Mail::assertNotSent(InstallerLeadMail::class, fn (InstallerLeadMail $mail): bool => $mail->hasTo('off@example.com'));
    }

    private function profile(array $data): InstallerProfile
    {
        $this->n++;

        $user = User::factory()->create(['email' => 'user'.$this->n.'@example.com']);

        return InstallerProfile::query()->create(array_merge([
            'user_id' => $user->id,
            'slug' => 'installer-'.$this->n,
            'contact_name' => 'Монтажник '.$this->n,
            'status' => 'active',
            'is_published' => true,
            'lead_notifications' => true,
        ], $data));
    }

    private function request(array $data): InstallRequest
    {
        $request = InstallRequest::query()->create(array_merge([
            'customer_name' => 'Иван Клиент',
            'customer_phone' => '+375291112233',
            'specialization' => 'heating',
            'source' => 'installers_page',
            'status' => 'new',
        ], array_diff_key($data, ['created_at' => 1])));
        if (isset($data['created_at'])) {
            $request->forceFill(['created_at' => $data['created_at']])->save();
        }

        return $request;
    }
}
