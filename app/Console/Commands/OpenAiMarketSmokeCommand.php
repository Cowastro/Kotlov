<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiMarketSmokeCommand extends Command
{
    protected $signature = 'market:openai-smoke {--web : Выполнить один реальный web search без данных каталога}';

    protected $description = 'Безопасно проверяет доступ production-сервера к OpenAI без вывода ключа';

    public function handle(): int
    {
        $apiKey = trim((string) config('services.ai.openai_key'));
        $model = trim((string) config('services.ai.openai_research_model', 'gpt-6.1-sol'));

        if ($apiKey === '') {
            $this->error('openai_key_configured=0');

            return self::FAILURE;
        }

        $this->line('openai_key_configured=1');

        try {
            $modelResponse = $this->request($apiKey)
                ->get('https://api.openai.com/v1/models/'.rawurlencode($model));

            if (! $modelResponse->successful()) {
                $this->error('model_access=0 http_status='.$modelResponse->status());

                return self::FAILURE;
            }

            $this->line('model_access=1');

            if (! $this->option('web')) {
                return self::SUCCESS;
            }

            $searchResponse = $this->request($apiKey)
                ->post((string) config('services.ai.openai_responses_url', 'https://api.openai.com/v1/responses'), [
                    'model' => $model,
                    'store' => false,
                    'tools' => [[
                        'type' => 'web_search',
                        'search_context_size' => 'low',
                        'external_web_access' => true,
                    ]],
                    'tool_choice' => 'required',
                    'max_output_tokens' => 800,
                    'reasoning' => ['effort' => 'low'],
                    'input' => 'Найди официальную страницу OpenAI о модели gpt-6.1-sol и ответь одним коротким предложением.',
                ]);

            if (! $searchResponse->successful()) {
                $this->error('web_search=0 http_status='.$searchResponse->status());

                return self::FAILURE;
            }

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

    private function request(string $apiKey)
    {
        $request = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->retry(1, 1000, throw: false);
        $proxy = trim((string) config('services.ai.openai_proxy'));

        return $proxy === '' ? $request : $request->withOptions(['proxy' => $proxy]);
    }
}
