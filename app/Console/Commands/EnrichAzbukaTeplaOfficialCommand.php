<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrichAzbukaTeplaOfficialCommand extends Command
{
    protected $signature = 'supplier:enrich-azbuka-tepla-official
        {--apply : Write images, characteristics and content}
        {--brand= : Process one brand}
        {--all-linked : Include older linked products; default is products created today}
        {--force : Re-enrich cards that already have local images and at least three characteristics}
        {--limit=0 : Maximum products, 0 means all}
        {--sleep=500 : Delay between source requests in milliseconds}';

    protected $description = 'Enrich Azbuka Tepla cards from verified manufacturer product pages.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $sleep = max(300, (int) $this->option('sleep'));

        $query = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('s.code', 'azbuka-tepla')
            ->where('p.is_archived', false)
            ->select(['sp.id as supplier_product_id', 'sp.raw', 'p.id', 'p.name', 'p.images', 'p.specs', 'p.content', 'b.name as brand'])
            ->orderBy('p.id');

        if (! $this->option('all-linked')) {
            $query->whereDate('p.created_at', now()->toDateString());
        }
        if ($brand = trim((string) $this->option('brand'))) {
            $query->whereRaw('LOWER(b.name) = ?', [mb_strtolower($brand)]);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $stats = ['scanned' => 0, 'mapped' => 0, 'enriched' => 0, 'images' => 0, 'specs' => 0, 'unmapped' => 0, 'errors' => 0];

        foreach ($query->get()->unique('id') as $row) {
            if (! $this->option('force') && $this->hasCompleteSourceData($row->images, $row->specs)) {
                continue;
            }

            $stats['scanned']++;
            $raw = $this->decodeRaw($row->raw);
            $model = trim((string) ($raw['model'] ?? $raw['partner_model'] ?? ''));
            $sourceUrl = $this->officialSource((string) $row->brand, $model, (string) $row->name);
            if ($sourceUrl === null) {
                $stats['unmapped']++;
                continue;
            }

            $stats['mapped']++;
            $product = Product::query()->find((int) $row->id);
            if (! $product) {
                $stats['errors']++;
                continue;
            }

            try {
                $result = $enricher->enrich($product, $sourceUrl, [
                    'preview_only' => ! $apply,
                    'replace_images' => false,
                    'update_images' => true,
                    'update_specs' => true,
                    'replace_specs' => true,
                    'min_specs_to_replace' => 3,
                    'update_service' => true,
                    'update_documents' => true,
                    'update_video' => true,
                    'update_content' => true,
                    'min_specs_for_ai' => 3,
                    'require_images_for_ai' => true,
                ]);

                $stats['images'] += (int) ($apply
                    ? ($result['images_saved'] ?? 0)
                    : ($result['images_found'] ?? 0));
                $stats['specs'] += (int) ($result['specs_found'] ?? 0);
                if ($apply && ($result['updated_fields'] ?? []) !== []) {
                    DB::table('supplier_products')->where('id', (int) $row->supplier_product_id)->update([
                        'source_url' => $sourceUrl,
                        'updated_at' => now(),
                    ]);
                    $stats['enriched']++;
                } elseif (! $apply) {
                    $stats['enriched']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('#%d %s: %s', $row->id, $row->name, $e->getMessage()));
            }

            usleep($sleep * 1000);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.azbuka_official.{$key}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function officialSource(string $brand, string $model, string $name): ?string
    {
        $brandKey = mb_strtolower(trim($brand));
        $text = trim($model.' '.$name);
        $ascii = Str::upper(str_replace([' ', '-', '_', '.', '/', 'М'], '', $text));

        if ($brandKey === 'auraton') {
            foreach (['aquila-set', 'auriga', 'cetus', 'libra-set', 'tucana-set'] as $slug) {
                if (str_contains(Str::slug($text), $slug)) {
                    return 'https://www.auraton.pl/en/offer/thermostats/auraton-'.$slug;
                }
            }
        }

        if ($brandKey === 'valfex' && preg_match('/ALPHA(25)(40|60|80)(130|180)/', $ascii, $match)) {
            return sprintf(
                'https://valfex.ru/catalog/seriya-alpha/tsirkulyatsionnyy-energoeffektivnyy-nasos-valfex-alpha-%s-%s/',
                $match[1],
                $match[2]
            );
        }

        if ($brandKey === 'salus') {
            return match (true) {
                str_contains($ascii, 'RT310') => 'https://saluscontrols.com/global/product/rt310-wired-non-programmable-thermostat/',
                str_contains($ascii, 'RT510') => 'https://saluscontrols.com/global/product/rt510-wired-electronic-thermostat-programmable/',
                str_contains($ascii, '091FLV2') => 'https://saluscontrols.com/global/product/091flv2-wired-electronic-thermostat-programmable/',
                default => null,
            };
        }

        if ($brandKey === 'barberi') {
            if (str_contains($ascii, '27B040N4P3') || str_contains($ascii, '27B040N4T3')) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b3-regulating-groups-and-heating-components/recessed-regulating-groups/recessed-regulating-groups-bdhb/27BN';
            }
            if (preg_match('/(?:^|[^0-9])(460|461)[0-9A-Z]+/', $ascii)) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b2-rotary-mixing-valves-and-actuators/3-way-mixing-valves/460';
            }
            if (str_contains($ascii, 'V07')) {
                foreach (['AAL2', 'ABL2', 'BAL2', 'BBL2', 'AA', 'AB', 'BA', 'BB'] as $suffix) {
                    if (str_contains($ascii, $suffix)) {
                        return 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/thermostatic-mixing-valves-l-comfort/V07'.$suffix;
                    }
                }
            }
        }

        if ($brandKey === 'kospel') {
            if (preg_match('/SWK(?:120|140)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/swk-termo-top.html';
            }
            if (preg_match('/SW(?:120|251|301)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/sw-i-swz-termo-max.html';
            }
            if (preg_match('/WP.*(?:100|120|140)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/wp-i-wpw.html';
            }
        }

        if ($brandKey === 'tesy') {
            return match (true) {
                str_contains($ascii, '301391') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-two-heat-exchangers/?product=ev-10-7s2-300-65',
                str_contains($ascii, '301394') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-12s-300-65',
                str_contains($ascii, '301395') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-15s-500-75',
                str_contains($ascii, '301396') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-two-heat-exchangers/?product=ev-15-7s2-500-75',
                default => null,
            };
        }

        return null;
    }

    private function decodeRaw(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasCompleteSourceData(mixed $images, mixed $specs): bool
    {
        return count($this->decodeRaw($images)) > 0
            && count($this->decodeRaw($specs)) >= 3;
    }
}
