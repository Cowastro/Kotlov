<?php

namespace Tests\Feature;

use App\Filament\Pages\PartnerFunnel;
use App\Http\Middleware\CapturePartnerAttribution;
use App\Http\Middleware\ProtectPublicForm;
use App\Models\B2bClient;
use App\Models\User;
use App\Services\PartnerFunnelReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_touch_reaches_installer_and_company_applications(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ProtectPublicForm::class);
        $cookie = CapturePartnerAttribution::COOKIE;
        $this->get('/partners')->assertOk()->assertCookieMissing($cookie);
        $response = $this->get('/partners?utm_source=search&utm_medium=cpc&utm_campaign=autumn&ref=demo');
        $response->assertOk()->assertCookie($cookie);
        $value = $response->getCookie($cookie)->getValue();
        $this->withCookie($cookie, $value)->get('/partners?utm_source=other')->assertCookieMissing($cookie);

        $this->post(route('partners.apply-installer'), [
            'contact_name' => 'Тестовый монтажник', 'phone' => '+375291234567',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('installer_applications', ['utm_source' => 'search', 'utm_medium' => 'cpc', 'utm_campaign' => 'autumn', 'ref' => 'demo']);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.b2b-request'), [
            'client_type' => 'wholesale', 'company_name' => 'Тестовая компания',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('b2b_clients', ['user_id' => $user->id, 'utm_source' => 'search', 'ref' => 'demo']);
    }

    public function test_report_groups_cohort_and_counts_first_paid_order_once(): void
    {
        $approved = $this->client('approved', 'search');
        $this->client('pending', 'search');
        $this->client('approved', null, 'installer');
        foreach (['2026-11-03', '2026-11-04'] as $date) {
            DB::table('b2b_orders')->insert([
                'number' => $date, 'b2b_client_id' => $approved->id, 'paid_at' => $date,
                'payment_status' => 'paid', 'status' => 'completed', 'contact_name' => 'Тест',
                'contact_phone' => '+375291234567', 'company_name' => 'Тест',
            ]);
        }

        $rows = app(PartnerFunnelReport::class)->rows('2026-11-01', '2026-12-01')->keyBy('partner_type');
        $wholesale = $rows['wholesale'];
        $this->assertEquals(['search', 2, 1, 1], [
            $wholesale->source, $wholesale->applications, $wholesale->approved, $wholesale->paid,
        ]);
        $this->assertNull($rows['installer']->source);
        $this->assertEquals(0, $rows['installer']->paid);
    }

    public function test_report_is_restricted_to_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]))
            ->get(PartnerFunnel::getUrl())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->get(PartnerFunnel::getUrl())->assertOk()->assertSeeText('Воронка партнёров');
    }

    private function client(string $status, ?string $source, string $type = 'wholesale'): B2bClient
    {
        $client = new B2bClient([
            'user_id' => User::factory()->create()->id, 'status' => $status,
            'partner_type' => $type, 'company_name' => 'Тест',
        ]);
        $client->forceFill(['created_at' => '2026-11-01', 'utm_source' => $source])->save();

        return $client;
    }
}
