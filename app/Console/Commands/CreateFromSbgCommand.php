<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationProductCreator;
use App\Services\Marketplaces\DealByClient;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A 1C row that has no card yet becomes a card from the same product on sbg.by (our second site,
 * its prices are the right ones): name, retail price, description and photos, and the row is linked.
 * With --relink a row linked to a wrong card is taken off it first.
 *
 *   php artisan integration:create-from-sbg УТ-00009074:4603789200959:aksessuary-dlya-bani            # list
 *   php artisan integration:create-from-sbg УТ-00009074:4603789200959:aksessuary-dlya-bani --relink --apply
 */
class CreateFromSbgCommand extends Command
{
    protected $signature = 'integration:create-from-sbg
        {pairs* : 1C_CODE:SBG_ARTICLE:CATEGORY_SLUG}
        {--source=onec : Integration source code}
        {--relink : Take the row off the card it is linked to now}
        {--apply : Create the cards and link the rows}';

    protected $description = 'Create cards for 1C rows from the same products on sbg.by';

    private const PAGE_SIZE = 100;

    public function handle(DealByClient $client, IntegrationProductCreator $creator, ProductSourceEnricher $enricher): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $wanted = collect((array) $this->argument('pairs'))->map(fn ($pair) => array_pad(explode(':', (string) $pair, 3), 3, ''));
        $site = $this->siteProducts($client, $wanted->pluck(1)->map(fn ($sku) => Str::upper(trim($sku)))->all());
        $stats = ['pairs' => $wanted->count(), 'ready' => 0, 'created' => 0, 'relinked' => 0, 'skipped' => 0];

        foreach ($wanted as [$code, $article, $categorySlug]) {
            $item = IntegrationProduct::query()->where('integration_source_id', $source?->id)
                ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
            $remote = $site[Str::upper(trim($article))] ?? null;
            $category = Category::query()->where('slug', $categorySlug)->first();
            $reason = match (true) {
                ! $item => 'no_row',
                ! $remote => 'not_on_site',
                ! $category => 'no_category',
                $item->product_id && ! $this->option('relink') => 'linked',
                default => null,
            };
            if ($reason !== null) {
                $stats['skipped']++;
                $this->line(sprintf('audit.create_from_sbg.skipped=%s:%s', Str::slug($code) ?: 'none', $reason));

                continue;
            }
            $stats['ready']++;
            $this->line(sprintf('audit.create_from_sbg.card=%d:%s:%s:%s:%d', $item->id, Str::slug(Str::limit($remote['name'], 80, '')),
                number_format($remote['price'], 2, '.', ''), $category->slug, count($remote['images'])));
            if (! $this->option('apply')) {
                continue;
            }

            if ($item->product_id) {
                $this->unlink($item);
                $stats['relinked']++;
            }
            $description = trim(strip_tags(html_entity_decode($remote['description'], ENT_QUOTES | ENT_HTML5)));
            $product = $creator->createAndLink($item->fresh(), [
                'name' => $remote['name'],
                'h1' => $remote['name'],
                'category_id' => $category->id,
                'retail_price' => $remote['price'],
                'short_description' => $description !== '' ? Str::limit(preg_replace('/\s+/u', ' ', $description), 300, '…') : null,
                'content' => $remote['description'] !== '' ? $remote['description'] : null,
                'meta_title' => Str::limit($remote['name'], 60, '').' — купить в %city%',
                'meta_description' => Str::limit($remote['name'].'. Цена, наличие и доставка по Беларуси.', 175, ''),
            ]);
            if (! $product) {
                continue;
            }
            $stats['created']++;
            if ($remote['images'] !== []) {
                $enricher->enrichFromParsed($product, $remote['images'][0], ['images' => []], [
                    'verified_images' => $remote['images'], 'update_images' => true, 'replace_images' => true, 'update_specs' => false,
                    'update_service' => false, 'update_documents' => false, 'update_video' => false, 'update_content' => false,
                ]);
            }
            $this->line(sprintf('audit.create_from_sbg.created=%d:%d', $item->id, $product->id));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.create_from_sbg.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** The row leaves the wrong card, and so does the price of the row the 1C sync put on it. */
    private function unlink(IntegrationProduct $item): void
    {
        DB::transaction(function () use ($item): void {
            $article = trim((string) ($item->external_sku ?: $item->external_code));
            if ($supplierId = $item->source?->supplier_id) {
                DB::table('supplier_products')->where('product_id', $item->product_id)->where('supplier_id', $supplierId)
                    ->where('supplier_article', $article)->delete();
            }
            $item->forceFill(['product_id' => null, 'match_status' => 'unmatched', 'match_method' => null, 'match_confidence' => null, 'matched_at' => null])->save();
        });
    }

    /**
     * @param  list<string>  $articles
     * @return array<string,array{name:string,price:float,description:string,images:list<string>}>
     */
    private function siteProducts(DealByClient $client, array $articles): array
    {
        $found = [];
        $lastId = null;
        for ($page = 1; $page <= 2000 && count($found) < count($articles); $page++) {
            $items = collect($client->listProducts(self::PAGE_SIZE, $lastId)['products'] ?? [])->filter(fn ($item) => is_array($item))->values();
            foreach ($items as $item) {
                $sku = Str::upper(trim((string) ($item['sku'] ?? '')));
                if (! in_array($sku, $articles, true) || ! is_numeric($item['price'] ?? null) || (float) $item['price'] <= 0) {
                    continue;
                }
                $images = array_values(array_filter(array_merge([(string) ($item['main_image'] ?? '')],
                    array_map(fn ($image) => is_array($image) ? (string) ($image['url'] ?? '') : (string) $image, (array) ($item['images'] ?? [])))));
                $found[$sku] = [
                    'name' => trim((string) ($item['name'] ?? '')),
                    'price' => round((float) $item['price'], 2),
                    'description' => trim((string) ($item['description'] ?? '')),
                    // The API gives previews («…_w328_h250_name.jpg»); the same URL without the size is the full photo.
                    'images' => array_values(array_unique(array_map(fn (string $url) => (string) preg_replace('/_w\d+_h\d+_/', '_', $url), $images))),
                ];
            }
            if ($items->count() < self::PAGE_SIZE) {
                break;
            }
            $next = (int) data_get($items->last(), 'id');
            if ($next <= 0 || $next === $lastId) {
                break;
            }
            $lastId = $next;
        }

        return $found;
    }
}
