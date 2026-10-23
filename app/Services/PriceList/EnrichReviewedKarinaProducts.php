<?php

namespace App\Services\PriceList;

use App\Models\Product;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class EnrichReviewedKarinaProducts
{
    public const OFFICIAL_HOST = 'karina.market';
    public const CREATED_VIA = 'karina_issue_34';
    public const PLAN_SCHEMA = 'karina-official-enrichment-v1';

    public function buildPlan(array $report): array
    {
        $this->validateReport($report);
        $catalog = [];
        foreach (range(1, 9) as $page) {
            $response = Http::retry(2, 200)->timeout(25)->withHeaders([
                'User-Agent' => 'KOTLOV catalog verifier/1.0',
            ])->get('https://'.self::OFFICIAL_HOST.'/elektricheskie-pechi/', ['page' => $page])->throw();
            preg_match_all(
                '~href="(https://karina\.market/[^"]*/elektricheskaya-pech-karina-[^"?]+)"~u',
                $response->body(),
                $matches
            );
            foreach ($matches[1] ?? [] as $url) {
                $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $catalog[basename(parse_url($url, PHP_URL_PATH))] = $url;
            }
        }

        $created = collect($report['products'] ?? [])
            ->filter(fn (array $row): bool => ($row['created_via'] ?? null) === self::CREATED_VIA)
            ->values();
        $matched = [];
        $missing = [];
        foreach ($created as $product) {
            $slug = $this->officialSlug((string) $product['name']);
            if (isset($catalog[$slug])) {
                $matched[(int) $product['id']] = ['product' => $product, 'url' => $catalog[$slug]];
            } else {
                $missing[] = ['product_id' => (int) $product['id'], 'reason' => 'official_page_not_found'];
            }
        }

        $pages = [];
        foreach (array_chunk($matched, 8, true) as $chunk) {
            $responses = Http::pool(fn (Pool $pool): array => collect($chunk)->map(
                fn (array $row, int $id) => $pool->as((string) $id)->timeout(25)->withHeaders([
                    'User-Agent' => 'KOTLOV catalog verifier/1.0',
                ])->get($row['url'])
            )->values()->all());
            foreach ($responses as $id => $response) {
                if ($response instanceof \Throwable || ! $response->successful()) {
                    $pages[(int) $id] = null;
                    continue;
                }
                $pages[(int) $id] = $this->parsePage($response->body(), $matched[(int) $id]['url']);
            }
        }

        $entries = [];
        foreach ($matched as $id => $row) {
            $data = $pages[$id] ?? null;
            $reasons = [];
            if (! $data) {
                $reasons[] = 'official_page_unavailable';
            } else {
                if ($data['image'] === null) {
                    $reasons[] = 'official_image_missing';
                }
                if (mb_strlen(strip_tags($data['content'])) < 80) {
                    $reasons[] = 'official_description_missing';
                }
                if (count($data['specs']) < 3) {
                    $reasons[] = 'official_specs_incomplete';
                }
            }
            $entries[] = [
                'product_id' => $id,
                'name' => $row['product']['name'],
                'price_byn' => (float) $row['product']['price'],
                'url' => $row['url'],
                'image' => $data['image'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'content' => $data['content'] ?? '',
                'specs' => $data['specs'] ?? [],
                'blocked_reasons' => $reasons,
                'eligible_for_activation' => $reasons === [],
            ];
        }

        return [
            'schema' => self::PLAN_SCHEMA,
            'generated_at' => now()->toIso8601String(),
            'official_host' => self::OFFICIAL_HOST,
            'workbook_sha256' => $report['workbook']['sha256'],
            'entries' => $entries,
            'missing' => $missing,
            'summary' => [
                'created_products' => $created->count(),
                'official_pages' => count($matched),
                'eligible' => count(array_filter($entries, fn ($row) => $row['eligible_for_activation'])),
                'incomplete' => count(array_filter($entries, fn ($row) => ! $row['eligible_for_activation'])),
                'not_found' => count($missing),
            ],
        ];
    }

    public function apply(array $report, array $plan, bool $apply = false): array
    {
        $this->validateReport($report);
        if (($plan['schema'] ?? '') !== self::PLAN_SCHEMA
            || ($plan['official_host'] ?? '') !== self::OFFICIAL_HOST
            || ! hash_equals((string) $report['workbook']['sha256'], (string) ($plan['workbook_sha256'] ?? ''))) {
            throw new RuntimeException('Official KARINA enrichment plan is invalid.');
        }

        return DB::transaction(function () use ($report, $plan, $apply): array {
            $snapshots = collect($report['products'] ?? [])->keyBy('id');
            $eligible = collect($plan['entries'] ?? [])
                ->filter(fn (array $entry): bool => ($entry['eligible_for_activation'] ?? false) === true)
                ->values();
            if ($eligible->isEmpty()) {
                throw new RuntimeException('No reviewed official KARINA cards are eligible.');
            }
            if ($eligible->pluck('product_id')->unique()->count() !== $eligible->count()) {
                throw new RuntimeException('Official KARINA plan contains duplicate products.');
            }

            $products = Product::query()->whereIn('id', $eligible->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($eligible as $entry) {
                $id = (int) $entry['product_id'];
                $product = $products->get($id);
                $snapshot = $snapshots->get($id);
                if (! $product || ! $snapshot
                    || $product->created_via !== self::CREATED_VIA
                    || $product->is_active || $product->is_archived
                    || $product->name !== $entry['name']
                    || abs((float) $product->price - (float) $entry['price_byn']) > 0.001
                    || (string) $snapshot['name'] !== (string) $entry['name']
                    || parse_url((string) $entry['url'], PHP_URL_HOST) !== self::OFFICIAL_HOST
                    || parse_url((string) $entry['image'], PHP_URL_HOST) !== self::OFFICIAL_HOST
                    || mb_strlen(strip_tags((string) $entry['content'])) < 80
                    || count($entry['specs'] ?? []) < 3) {
                    throw new RuntimeException('KARINA catalog changed after official-content dry run.');
                }
            }

            $result = [
                'already_applied' => false,
                'planned' => $eligible->count(),
                'activated' => 0,
                'left_hidden' => (int) ($plan['summary']['created_products'] ?? 0) - $eligible->count(),
                'official_pages' => (int) ($plan['summary']['official_pages'] ?? 0),
            ];
            if (! $apply) {
                return $result;
            }

            foreach ($eligible as $entry) {
                $products->get((int) $entry['product_id'])->forceFill([
                    'short_description' => $entry['short_description'],
                    'content' => $entry['content'],
                    'images' => [$entry['image']],
                    'specs' => $entry['specs'],
                    'content_source_url' => $entry['url'],
                    'is_active' => true,
                    'in_stock' => false,
                    'availability_status' => Product::AVAILABILITY_CHECK,
                    'stock_qty' => null,
                    'requires_price_confirmation' => false,
                    'meta_title' => Str::limit($entry['name'].' — купить в Беларуси | KOTLOV', 255, ''),
                    'meta_description' => Str::limit((string) $entry['short_description'], 255, ''),
                ])->save();
                $result['activated']++;
            }

            return $result;
        });
    }

    private function validateReport(array $report): void
    {
        if (($report['schema'] ?? '') !== 'karina-audit-v1'
            || ($report['source_currency'] ?? '') !== 'RUB'
            || ($report['currency_confirmed'] ?? false) !== true
            || ($report['applied'] ?? true) !== false
            || ! preg_match('/^[a-f0-9]{64}$/', (string) ($report['workbook']['sha256'] ?? ''))) {
            throw new RuntimeException('A confirmed read-only KARINA audit is required.');
        }
    }

    private function parsePage(string $html, string $url): ?array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $image = trim((string) $xpath->evaluate('string(//meta[@property="og:image"]/@content)'));
        if (parse_url($image, PHP_URL_SCHEME) !== 'https' || parse_url($image, PHP_URL_HOST) !== self::OFFICIAL_HOST) {
            $image = null;
        }

        $paragraphs = [];
        foreach ($xpath->query('//*[@id="tab-description"]//*[@itemprop="description"]//p') ?: [] as $node) {
            $text = preg_replace('/\s+/u', ' ', trim($node->textContent)) ?? '';
            if (mb_strlen($text) >= 5 && ! in_array($text, $paragraphs, true)) {
                $paragraphs[] = $text;
            }
        }
        $content = implode('', array_map(fn (string $text): string => '<p>'.e($text).'</p>', $paragraphs));
        $short = $paragraphs === [] ? null : Str::limit($paragraphs[0], 500, '');

        $specs = [];
        foreach ($xpath->query('//*[@id="tab-specification"]//tr[td[1] and td[2]]') ?: [] as $row) {
            $cells = $xpath->query('./td', $row);
            $key = preg_replace('/\s+/u', ' ', trim($cells->item(0)?->textContent ?? '')) ?? '';
            $value = preg_replace('/\s+/u', ' ', trim($cells->item(1)?->textContent ?? '')) ?? '';
            if ($key !== '' && $value !== '' && ! isset($specs[$key])) {
                $specs[$key] = $value;
            }
        }

        return [
            'url' => $url,
            'image' => $image,
            'short_description' => $short,
            'content' => $content,
            'specs' => collect($specs)->map(fn (string $value, string $key): array => compact('key', 'value'))->values()->all(),
        ];
    }

    private function officialSlug(string $name): string
    {
        $value = mb_strtolower(str_replace('Электрическая печь', 'elektricheskaya-pech', $name));
        $value = strtr($value, [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    }
}
