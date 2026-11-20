<?php

namespace Tests\Feature;

use App\Http\Middleware\CapturePartnerAttribution;
use App\Http\Middleware\ProtectPublicForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerAttributionTest extends TestCase
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
}
