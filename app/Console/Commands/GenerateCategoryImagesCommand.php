<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateCategoryImagesCommand extends Command
{
    protected $signature = 'categories:generate-images
        {--slugs= : Comma-separated slugs to generate (default: all missing)}
        {--force : Regenerate even if image already exists}';

    protected $description = 'Generate category banner images via DALL-E 3';

    private array $prompts = [
        'radiatory'                        => 'Professional product photo of modern white steel panel radiators and aluminum radiators for home heating, arranged on clean white background, minimalist style, commercial photography, no text',
        'truby-i-fitingi'                  => 'Professional product photo of polypropylene pipes, metal fittings, ball valves and pipe connectors for heating systems, arranged on clean white background, minimalist commercial photography, no text',
        'teplyj-pol'                       => 'Professional product photo of underfloor heating system components: heating cable mat, thermostat controller, on clean light gray background, minimalist style, no text',
        'elektricheskie-konvektoryi'       => 'Professional product photo of modern white electric convector wall heater, sleek minimalist design on white background, commercial photography, no text',
        'komplektuyushhie-dlya-otopleniya' => 'Professional product photo of heating system components: expansion tank, circulating pump, pressure gauge, valves on clean white background, minimalist commercial photography, no text',
        'filtry'                           => 'Professional product photo of water filtration systems, magnetic dirt separator and mesh filters for heating systems on clean white background, minimalist style, no text',
    ];

    public function handle(): int
    {
        $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');

        if (! $apiKey) {
            $this->error('OPENAI_API_KEY not set in .env');
            return self::FAILURE;
        }

        $slugsOpt = $this->option('slugs');
        $slugs    = $slugsOpt ? explode(',', $slugsOpt) : array_keys($this->prompts);
        $force    = $this->option('force');
        $outDir   = public_path('img/popular');

        foreach ($slugs as $slug) {
            $slug = trim($slug);

            if (! isset($this->prompts[$slug])) {
                $this->warn("No prompt for slug: {$slug}");
                continue;
            }

            $outFile = "{$outDir}/{$slug}.jpg";

            if (file_exists($outFile) && ! $force) {
                $this->line("Skip {$slug} — already exists");
                continue;
            }

            $this->info("Generating: {$slug}...");

            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model'   => 'gpt-image-1',
                    'prompt'  => $this->prompts[$slug],
                    'n'       => 1,
                    'size'    => '1024x1024',
                    'quality' => 'medium',
                ]);

            if (! $response->ok()) {
                $this->error("Failed {$slug}: " . $response->body());
                continue;
            }

            $item = $response->json('data.0');
            if (isset($item['url'])) {
                $imageData = Http::timeout(30)->get($item['url'])->body();
            } elseif (isset($item['b64_json'])) {
                $imageData = base64_decode($item['b64_json']);
            } else {
                $this->error("No image data returned for {$slug}: " . json_encode($item));
                continue;
            }

            file_put_contents($outFile, $imageData);

            $this->info("  → saved: img/popular/{$slug}.jpg");

            // Rate limit: 5 images/min → wait 13s between requests
            if (next($slugs) !== false) {
                $this->line('  waiting 13s (rate limit)...');
                sleep(13);
            }
        }

        $this->newLine();
        $this->info('Done. Now update $popularImages in catalog-index.blade.php if needed.');

        return self::SUCCESS;
    }
}
