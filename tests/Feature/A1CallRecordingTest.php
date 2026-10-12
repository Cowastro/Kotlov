<?php

namespace Tests\Feature;

use App\Models\TelephonyCall;
use App\Models\TelephonyConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class A1CallRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_securely_stream_a1_recording_without_exposing_token(): void
    {
        Sleep::fake();
        Http::fake([
            'https://vats.a1.by/crm-api/open-api/v1/record*' => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        TelephonyConnection::query()->create([
            'provider' => 'a1',
            'company_id' => '3493',
            'access_token' => 'secret-access-token',
            'access_token_expires_at' => now()->addHour(),
            'is_enabled' => true,
        ]);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'recording-call',
            'direction' => 'inbound',
            'status' => 'answered',
            'handling_status' => 'unprocessed',
            'record_name' => '3493/2026-10-12/recording-file',
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('telephony.a1.recording', $call))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertContent('audio-bytes');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authentication', 'secret-access-token')
            && $request['company_id'] === '3493'
            && $request['filename'] === '3493/2026-10-12/recording-file');
    }

    public function test_customer_cannot_access_call_recording(): void
    {
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'private-recording-call',
            'direction' => 'inbound',
            'status' => 'answered',
            'handling_status' => 'unprocessed',
            'record_name' => 'private-file',
        ]);
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->actingAs($customer)
            ->get(route('telephony.a1.recording', $call))
            ->assertForbidden();
    }
}
