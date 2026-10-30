<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiMarketSmokeCommand extends Command
{
    protected $signature = 'market:openai-smoke {--web : Выполнить один реальный web search без данных каталога}';

    protected $description = 'Безопасно проверяет доступ production-сервера к провайдеру исследования рынка без вывода ключа';

    public function handle(): int
    {
        $provider = trim((string) config('services.ai.market_research_provider', 'deepseek'));
        [$apiKey, $model, $responsesUrl] = match ($provider) {
            'deepseek' => [
                trim((string) config('services.ai.api_key')),
                trim((string) config('services.ai.deepseek_research_model', 'deepseek-v4-pro')),
                trim((string) config('services.ai.deepseek_anthropic_url', 'https://api.deepseek.com/anthropic/v1/messages')),
            ],
            'azure' => [
                trim((string) config('services.ai.azure_openai_key')),
                trim((string) config('services.ai.azure_openai_research_model', 'gpt-5.5')),
                trim((string) config('services.ai.azure_openai_responses_url')),
            ],
            default => [
                trim((string) config('services.ai.openai_key')),
                trim((string) config('services.ai.openai_research_model', 'gpt-6.1-sol')),
                trim((string) config('services.ai.openai_responses_url', 'https://api.openai.com/v1/responses')),
            ],
        };

        if ($apiKey === '') {
            $this->error('openai_key_configured=0');

            return self::FAILURE;
        }

        $this->line('openai_key_configured=1');
        $this->line('openai_provider='.$provider);

        if ($model === '' || $responsesUrl === '') {
            $this->error('openai_endpoint_configured=0');

            return self::FAILURE;
        }

        try {
            if (! $this->option('web')) {
                $payload = $provider === 'deepseek'
                    ? [
                        'model' => $model,
                        'max_tokens' => 32,
                        'messages' => [['role' => 'user', 'content' => 'Ответь одним словом: OK']],
                    ]
                    : [
                        'model' => $model,
                        'store' => false,
                        'max_output_tokens' => 32,
                        'input' => 'Ответь одним словом: OK',
                    ];
                $probeResponse = $this->request($apiKey, $provider)
                    ->post($responsesUrl, $payload);

                if (! $probeResponse->successful()) {
                    $this->error('model_access=0 http_status='.$probeResponse->status());

                    return self::FAILURE;
                }

                $this->line('model_access=1');

                return self::SUCCESS;
            }

            $payload = $provider === 'deepseek'
                ? [
                    'model' => $model,
                    'max_tokens' => 800,
                    'messages' => [[
                        'role' => 'user',
                        'content' => 'Найди официальную страницу DeepSeek о доступных моделях и ответь одним коротким предложением.',
                    ]],
                    'tools' => [[
                        'type' => 'web_search_20250305',
                        'name' => 'web_search',
                        'max_uses' => 2,
                    ]],
                ]
                : $this->openAiWebPayload($model, $provider);

            $searchResponse = $this->request($apiKey, $provider)
                ->post($responsesUrl, $payload);

            if (! $searchResponse->successful()) {
                $this->error('web_search=0 http_status='.$searchResponse->status());

                return self::FAILURE;
            }

            $this->line('model_access=1');
            $calls = $provider === 'deepseek'
                ? collect((array) $searchResponse->json('content'))
                    ->whereIn('type', ['server_tool_use', 'web_search_tool_result'])
                    ->count()
                : collect((array) $searchResponse->json('output'))
                    ->where('type', 'web_search_call')
                    ->count();
            $this->line('web_search='.($calls > 0 ? '1' : '0'));
            $this->line('web_search_calls='.$calls);

            return $calls > 0 ? self::SUCCESS : self::FAILURE;
        } catch (ConnectionException $exception) {
            $this->error('network_access=0');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('openai_smoke=0');

            return self::FAILURE;
        }
    }

    private function request(string $apiKey, string $provider)
    {
        $request = Http::acceptJson()
            ->timeout(60)
            ->retry(1, 1000, throw: false);
        if ($provider === 'azure') {
            return $request->withHeaders(['api-key' => $apiKey]);
        }

        if ($provider === 'deepseek') {
            return $request->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ]);
        }

        $request = $request->withToken($apiKey);
        $proxy = trim((string) config('services.ai.proxy'));

        return $proxy === '' ? $request : $request->withOptions(['proxy' => $proxy]);
    }

    /** @return array<string, mixed> */
    private function openAiWebPayload(string $model, string $provider): array
    {
        $webSearch = [
            'type' => 'web_search',
            'search_context_size' => 'low',
        ];
        if ($provider === 'openai') {
            $webSearch['external_web_access'] = true;
        }

        return [
            'model' => $model,
            'store' => false,
            'tools' => [$webSearch],
            'tool_choice' => 'required',
            'max_output_tokens' => 800,
            'reasoning' => ['effort' => 'low'],
            'input' => 'Найди официальную страницу OpenAI о доступных моделях и ответь одним коротким предложением.',
        ];
    }
}
