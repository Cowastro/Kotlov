<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\TeplodvorOfferMatcher;
use Illuminate\Console\Command;

class AuditEurotermTeplodvorCommand extends Command
{
    protected $signature = 'supplier:audit-euroterm-teplodvor
        {--unlinked-only : Check only Euroterm offers without a product card}
        {--verify-pages : Fetch accepted Teplodvor pages and require usable content}
        {--sleep=250 : Delay between accepted page requests, milliseconds}';

    protected $description = 'Read-only cross-check of all Euroterm offers against the Teplodvor product index.';

    public function handle(TeplodvorOfferMatcher $matcher, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', 'euroterm')->firstOrFail();
        $indexPath = storage_path('teplodvor_index.json');
        $index = is_file($indexPath) ? json_decode((string) file_get_contents($indexPath), true) : [];
        if (! is_array($index) || $index === []) {
            $this->error('Teplodvor index is missing or empty.');

            return self::FAILURE;
        }

        $query = SupplierProduct::query()->where('supplier_id', $supplier->id)->orderBy('id');
        if ($this->option('unlinked-only')) {
            $query->whereNull('product_id');
        }

        $stats = [
            'index_urls' => count($index),
            'offers' => 0,
            'linked_offers' => 0,
            'unlinked_offers' => 0,
            'matched' => 0,
            'matched_linked' => 0,
            'matched_unlinked' => 0,
            'article_matches' => 0,
            'token_matches' => 0,
            'verified_pages' => 0,
            'usable_pages' => 0,
            'unusable_pages' => 0,
            'no_match' => 0,
            'errors' => 0,
        ];

        foreach ($query->get() as $offer) {
            $stats['offers']++;
            $linked = (int) $offer->product_id > 0;
            $stats[$linked ? 'linked_offers' : 'unlinked_offers']++;
            $raw = is_array($offer->raw) ? $offer->raw : [];
            $match = $matcher->match([
                'article' => $offer->supplier_article,
                'name' => $offer->supplier_name,
                'brand' => $raw['brand'] ?? '',
            ], $index);

            if (! $match['url']) {
                $stats['no_match']++;
                continue;
            }

            $stats['matched']++;
            $stats[$linked ? 'matched_linked' : 'matched_unlinked']++;
            $stats[$match['method'] === 'article' ? 'article_matches' : 'token_matches']++;
            if (! $linked) {
                $article = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', (string) $offer->supplier_article) ?: 'no-article';
                $this->line('candidate.euroterm_teplodvor.unlinked='.$article.'|'.$match['url']);
            }

            if (! $this->option('verify-pages')) {
                continue;
            }

            try {
                $parsed = $enricher->preview($match['url']);
                $usable = ($parsed['images'] ?? []) !== []
                    && (($parsed['specs'] ?? []) !== [] || trim((string) ($parsed['description'] ?? '')) !== '');
                $stats['verified_pages']++;
                $stats[$usable ? 'usable_pages' : 'unusable_pages']++;
            } catch (\Throwable $exception) {
                $stats['errors']++;
                report($exception);
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.euroterm_teplodvor.{$key}={$value}");
        }

        return $stats['errors'] > 0 && $stats['verified_pages'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
