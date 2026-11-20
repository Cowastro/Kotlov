<?php

namespace Tests\Feature;

use App\Services\SmsByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SmsByClientTemplateTest extends TestCase
{
    use RefreshDatabase;

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
