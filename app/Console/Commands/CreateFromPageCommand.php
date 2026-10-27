<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationProductCreator;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A 1C row with no card and no supplier price becomes a card from a shop page (21vek.by and the like):
 * the title and photos come from the page, the retail price is the market price given here.
 *
 *   php artisan integration:create-from-page УТ-00007767 pechnoe-i-kaminnoe-lite 181 https://www.21vek.by/sauna_boilers/23_balezinskijlmz.html
 *   … --apply
 */
class CreateFromPageCommand extends Command
{
    protected $signature = 'integration:create-from-page
        {code : 1C code of the row}
        {category : Category slug}
        {price : Retail price, BYN}
        {url : Shop page of the same product}
        {--source=onec : Integration source code}
        {--apply : Create the card and link the row}';

    protected $description = 'Create a card for a 1C row from a shop page at a market price';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    public function handle(IntegrationProductCreator $creator, ProductSourceEnricher $enricher): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $code = (string) $this->argument('code');
        $item = IntegrationProduct::query()->where('integration_source_id', $source?->id)
            ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
        $category = Category::query()->where('slug', (string) $this->argument('category'))->first();
        $price = round((float) str_replace(',', '.', (string) $this->argument('price')), 2);
        $url = (string) $this->argument('url');
        $html = $this->get($url);
        $name = trim(html_entity_decode(strip_tags((string) (preg_match('/<h1[^>]*>(.*?)<\/h1>/su', $html, $m) ? $m[1] : '')), ENT_QUOTES | ENT_HTML5));
        $reason = match (true) {
            ! $item => 'no_row',
            (bool) $item->product_id => 'linked',
            ! $category => 'no_category',
            $price <= 0 => 'no_price',
            $name === '' => 'no_page',
            default => null,
        };
        if ($reason !== null) {
            $this->line(sprintf('audit.create_from_page.skipped=%s:%s', Str::slug($code) ?: 'none', $reason));

            return self::SUCCESS;
        }
        $photos = $this->photos($url, $html);
        $this->line(sprintf('audit.create_from_page.card=%d:%s:%s:%s:%d', $item->id, Str::slug(Str::limit($name, 80, '')), number_format($price, 2, '.', ''),
            $category->slug, count($photos)));
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }
        $product = $creator->createAndLink($item, [
            'name' => $name,
            'h1' => $name,
            'category_id' => $category->id,
            'retail_price' => $price,
            'content_source_url' => $url,
            'meta_title' => Str::limit($name, 60, '').' — купить в %city%',
            'meta_description' => Str::limit($name.'. Цена, наличие и доставка по Беларуси.', 175, ''),
        ]);
        if ($product && $photos !== []) {
            $enricher->enrichFromParsed($product, $url, ['images' => []], [
                'verified_images' => $photos, 'update_images' => true, 'replace_images' => true, 'update_specs' => false,
                'update_service' => false, 'update_documents' => false, 'update_video' => false, 'update_content' => false,
            ]);
        }
        $this->line(sprintf('audit.create_from_page.created=%d:%d', $item->id, (int) $product?->id));

        return self::SUCCESS;
    }

    /** @return list<string> the product's own gallery (21vek names the files after the page), else the page image */
    private function photos(string $url, string $html): array
    {
        $slug = basename($url, '.html');
        preg_match_all('#https://cdn21vek\.by/img/galleries/[^"\\\\\s,]+\.(?:jpe?g|png|webp)#i', $html, $m);
        $own = collect($m[0])->filter(fn (string $image) => str_starts_with(basename($image), $slug))->unique()->take(4)->values()->all();
        if ($own !== []) {
            return $own;
        }

        return preg_match('/property="og:image"\s+content="([^"]+)"/', $html, $og) ? [html_entity_decode($og[1])] : [];
    }

    private function get(string $url): string
    {
        try {
            $response = Http::timeout(30)->withUserAgent(self::USER_AGENT)->retry(2, 500, throw: false)->get($url);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
