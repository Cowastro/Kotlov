<?php

namespace Tests\Feature;

use App\Models\TelephonyConnection;
use App\Services\Telephony\A1OpenApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class A1OpenApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_throttles_requests_and_retries_a1_rate_limit_responses(): void
    {
        Sleep::fake();
        Http::fakeSequence()
            ->push(['access_token' => 'access-token', 'refresh_token' => 'refresh-token'])
            ->push(['details' => ['Retry after: 1000 ms']], 429)
            ->push([['employeeId' => '42', 'name' => 'Алена']]);

        $connection = TelephonyConnection::query()->create([
            'provider' => 'a1',
            'company_id' => '3493',
            'primary_api_key' => 'primary-code',
            'is_enabled' => true,
        ]);

        $employees = app(A1OpenApiClient::class)->employees($connection);

        $this->assertSame('42', $employees[0]['employeeId']);
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(3);
    }
}
