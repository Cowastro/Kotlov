<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiMarketSmokeCommandTest extends TestCase
{
    public function test_it_fails_safely_when_key_is_missing(): void
    {
        config()->set('services.ai.market_research_provider', 'openai');
        config()->set('services.ai.openai_key', '');

        $this->artisan('market:openai-smoke', ['--web' => true])
            ->expectsOutputToContain('openai_key_configured=0')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_checks_model_and_web_search_without_exposing_the_key(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'openai',
            'services.ai.openai_key' => 'secret-test-key',
            'services.ai.openai_research_model' => 'gpt-6.1-sol',
            'services.ai.openai_responses_url' => 'https://api.openai.com/v1/responses',
        ]);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'web_search_call',
                    'status' => 'completed',
                ]],
            ]),
        ]);

        $this->artisan('market:openai-smoke', ['--web' => true])
            ->expectsOutputToContain('openai_key_configured=1')
            ->expectsOutputToContain('model_access=1')
            ->expectsOutputToContain('web_search=1')
            ->expectsOutputToContain('web_search_calls=1')
            ->doesntExpectOutputToContain('secret-test-key')
            ->assertSuccessful();
    }

    public function test_it_checks_azure_openai_without_exposing_the_key(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'azure',
            'services.ai.azure_openai_key' => 'azure-secret-key',
            'services.ai.azure_openai_responses_url' => 'https://example.openai.azure.com/openai/v1/responses',
            'services.ai.azure_openai_research_model' => 'market-research-deployment',
        ]);
        Http::fake([
            'https://example.openai.azure.com/openai/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'web_search_call',
                    'status' => 'completed',
                ]],
            ]),
        ]);

        $this->artisan('market:openai-smoke', ['--web' => true])
            ->expectsOutputToContain('openai_provider=azure')
            ->expectsOutputToContain('model_access=1')
            ->expectsOutputToContain('web_search=1')
            ->doesntExpectOutputToContain('azure-secret-key')
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->hasHeader('api-key', 'azure-secret-key')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_it_checks_deepseek_native_web_search_without_exposing_the_key(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_research_model' => 'deepseek-v4-pro',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
        ]);
        Http::fake([
            'https://api.deepseek.test/anthropic/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'server_tool_use', 'name' => 'web_search'],
                    ['type' => 'web_search_tool_result', 'content' => []],
                    ['type' => 'text', 'text' => 'Готово.'],
                ],
            ]),
        ]);

        $this->artisan('market:openai-smoke', ['--web' => true])
            ->expectsOutputToContain('openai_key_configured=1')
            ->expectsOutputToContain('openai_provider=deepseek')
            ->expectsOutputToContain('model_access=1')
            ->expectsOutputToContain('web_search=1')
            ->doesntExpectOutputToContain('deepseek-secret-key')
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->hasHeader('x-api-key', 'deepseek-secret-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request->data()['model'] === 'deepseek-v4-pro'
            && $request->data()['tools'][0]['type'] === 'web_search_20250305');
    }
}
