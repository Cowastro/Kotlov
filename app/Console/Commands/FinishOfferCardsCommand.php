<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiContentEnricher;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

/**
 * Cards created from a supplier offer in a КП get photos and specs at once;
 * the AI description takes longer, so it is written here (every 15 minutes)
 * from the same source page, or from the name and specs without one.
 *
 *   php artisan catalog:finish-offer-cards
 */
class FinishOfferCardsCommand extends Command
{
    protected $signature = 'catalog:finish-offer-cards {--limit=10 : Cards per run}';

    protected $description = 'Write the AI description of cards created from КП supplier offers';

    public function handle(): int
    {
        $stats = ['cards' => 0, 'content' => 0, 'errors' => 0];
        $ai = new AiContentEnricher;
        $cards = Product::query()->with('brand:id,name')
            ->where('created_via', Product::CREATED_VIA_COMMERCIAL_OFFER)
            ->where('is_archived', false)
            ->whereRaw('LENGTH(COALESCE(content, "")) < 200')
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();

        foreach ($cards as $product) {
            $stats['cards']++;
            try {
                $url = (string) $product->content_source_url;
                if (preg_match('#^https?://[^/]+/.+#', $url)) {
                    app(ProductSourceEnricher::class)->enrich($product, $url, [
                        'update_images' => false, 'update_specs' => false, 'update_documents' => false, 'update_video' => false, 'min_specs_for_ai' => 0,
                    ]);
                } elseif ($ai->isAvailable()) {
                    $specs = collect((array) $product->specs)->mapWithKeys(fn ($spec): array => is_array($spec) && isset($spec['key']) ? [$spec['key'] => (string) ($spec['value'] ?? '')] : [])->all();
                    $content = $ai->enrich($product->name, (string) $product->brand?->name, '', $specs);
                    if ($content && trim(strip_tags($content)) !== '') {
                        $short = $ai->shortDescription($product->name, (string) $product->brand?->name, $specs) ?: mb_substr(trim(strip_tags($content)), 0, 240);
                        $product->forceFill([
                            'content' => strip_tags($content, '<p><ul><li><strong>'),
                            'short_description' => mb_substr(trim($short), 0, 240),
                            'meta_description' => mb_substr(trim($short), 0, 250),
                        ])->save();
                    }
                }
                $stats['content'] += (int) (mb_strlen((string) $product->fresh()->content) >= 200);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("#{$product->id}: ".$e->getMessage());
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.offer_cards.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
