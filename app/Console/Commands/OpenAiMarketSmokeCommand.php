<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiMarketSmokeCommand extends Command
{
    protected $signature = 'market:openai-smoke {--web : Выполнить один реальный web search без данных каталога}';

    protected $description = 'Безопасно проверяет доступ production-сервера к OpenAI/Azure OpenAI без вывода ключа';

    public function handle(): int
    {
        $provider = trim((string) config('services.ai.market_openai_provider', 'openai')) === 'azure'
            ? 'azure'
            : 'openai';
        $apiKey = trim((string) config($provider === 'azure'
            ? 'services.ai.azure_openai_key'
            : 'services.ai.openai_key'));
        $model = trim((string) config($provider === 'azure'
            ? 'services.ai.azure_openai_research_model'
            : 'services.ai.openai_research_model', $provider === 'azure' ? 'gpt-5.5' : 'gpt-6.1-sol'));
        $responsesUrl = trim((string) config($provider === 'azure'
            ? 'services.ai.azure_openai_responses_url'
            : 'services.ai.openai_responses_url', $provider === 'azure' ? '' : 'https://api.openai.com/v1/responses'));

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
                $probeResponse = $this->request($apiKey, $provider)
                    ->post($responsesUrl, [
                        'model' => $model,
                        'store' => false,
                        'max_output_tokens' => 32,
                        'input' => 'Ответь одним словом: OK',
                    ]);

                if (! $probeResponse->successful()) {
                    $this->error('model_access=0 http_status='.$probeResponse->status());

                    return self::FAILURE;
                }

                $this->line('model_access=1');

                return self::SUCCESS;
            }

            $webSearch = [
                'type' => 'web_search',
                'search_context_size' => 'low',
            ];
            if ($provider === 'openai') {
                $webSearch['external_web_access'] = true;
            }

            $searchResponse = $this->request($apiKey, $provider)
                ->post($responsesUrl, [
                    'model' => $model,
                    'store' => false,
                    'tools' => [$webSearch],
                    'tool_choice' => 'required',
                    'max_output_tokens' => 800,
                    'reasoning' => ['effort' => 'low'],
                    'input' => 'Найди официальную страницу OpenAI о доступных моделях и ответь одним коротким предложением.',
                ]);

            if (! $searchResponse->successful()) {
                $this->error('web_search=0 http_status='.$searchResponse->status());

                return self::FAILURE;
            }

            $this->line('model_access=1');
            $calls = collect((array) $searchResponse->json('output'))
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

        $request = $request->withToken($apiKey);
        $proxy = trim((string) config('services.ai.openai_proxy'));

        return $proxy === '' ? $request : $request->withOptions(['proxy' => $proxy]);
    }
}
