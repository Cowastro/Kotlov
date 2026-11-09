<?php

namespace Tests\Feature;

use App\Filament\Pages\SmsByDiagnostics;
use App\Models\User;
use App\Services\SmsByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SmsByDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_check_send_and_confirm_one_test_without_enabling_registration_sms(): void
    {
        config([
            'services.sms_by.enabled' => false,
            'services.sms_by.token' => 'test-api-key',
            'services.sms_by.password_object_id' => '123',
            'services.sms_by.alphaname_id' => '',
        ]);
        Http::fake([
            '*/getBalance*' => Http::response(['status' => 'OK', 'currency' => 'BYN', 'result' => [['balance' => 100]]]),
            '*/getAlphanames*' => Http::response(['7229' => 'KOTLOV.BY']),
            '*/getPasswordObject*' => Http::response(['result' => ['type_id' => 'numbers', 'len' => 6]]),
            '*/sendSmsMessageWithCode*' => Http::response(['status' => 'ok', 'code' => '012345', 'sms_id' => 1234, 'parts' => 1]),
            '*/checkSMS*' => Http::response(['sms_id' => 1234, 'sent' => 100, 'delivered' => 105]),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $page = Livewire::test(SmsByDiagnostics::class)
            ->call('checkConnection')
            ->assertSee('100 BYN')
            ->assertSee('KOTLOV.BY');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/sendSmsMessageWithCode'));

        $page->set('phone', '+375 (29) 111-11-11')
            ->call('sendTest')
            ->assertSee('Сегментов 1')
            ->assertSee('ID SMS 1234')
            ->assertDontSee('012345')
            ->call('checkDelivery')
            ->assertSee('Доставлено')
            ->set('code', '012345')
            ->call('confirmCode')
            ->assertSee('Код подтверждён.')
            ->call('sendTest')
            ->assertHasErrors(['phone']);

        $sends = Http::recorded(fn (Request $request) => str_contains($request->url(), '/sendSmsMessageWithCode'));
        $this->assertCount(1, $sends);
        $this->assertSame(SmsByClient::CODE_MESSAGE, $sends->first()[0]['message']);
    }

    public function test_oversize_sms_template_is_rejected_before_contacting_provider(): void
    {
        Http::fake();
        config([
            'services.sms_by.enabled' => true,
            'services.sms_by.token' => 'test-api-key',
            'services.sms_by.password_object_id' => '123',
            'services.sms_by.alphaname_id' => '7229',
            'services.sms_by.message' => str_repeat('А', 65).' %CODE%',
        ]);

        try {
            app(SmsByClient::class)->sendVerificationCode('375291111111');
            $this->fail('Oversize template was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('одно SMS', $exception->getMessage());
            Http::assertNothingSent();
        }
    }
}
