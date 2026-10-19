<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichThermokraftOfficialCommand extends Command
{
    private const STEN_CATALOG_URL = 'https://sten.ru/upload/iblock/8f9/katalog-gk-sten-2018_1.pdf';

    protected $signature = 'supplier:enrich-thermokraft-official
        {--apply : Write verified official data}
        {--limit=3 : Maximum cards per run}
        {--overwrite-images : Replace the current gallery with the official gallery}
        {--replace-specs : Replace characteristics with the official set}
        {--sleep=900 : Delay between official page requests, ms}';

    protected $description = 'Gradually enrich exact Thermokraft TENKO cards from official TENKO pages.';

    /**
     * Only exact, manually verified modifications belong here. Similar power alone
     * is not sufficient: voltage, thread and immersion length must also match.
     *
     * @var array<int,array{name:string,url:string,required:array<int,string>,images_only?:bool,image_limit?:int}>
     */
    private const VERIFIED = [
        [
            'name' => 'ТЭН трубчатый 4500 Вт/220В крепление гайка латунная 1,5" L=300мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-4500vt-kreplenie-gayka-latunnaya-15-l300mm',
            'required' => ['4500', '220', '1,5', '300'],
        ],
        [
            'name' => 'ТЭН трубчатый 9000 Вт/380В крепление гайка латунная 2" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-9000vt-kreplenie-gayka-latunnaya-380v-2-l420mm',
            'required' => ['9000', '380', '2', '420'],
        ],
        [
            'name' => 'ТЭН трубчатый 12000 Вт/380В крепление гайка латунная 2" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-1200vt-kreplenie-gayka-latunnaya-2-l-420-mm',
            'required' => ['12000', '380', '2', '420'],
        ],
        [
            'name' => 'ТЭН трубчатый 4500 Вт/220В крепление гайка латунная 1 1/4" L=300мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-4500vt-kreplenie-gayka-latunnaya-114-l300mm',
            'required' => ['4500', '220', '11/4', '300'],
        ],
        [
            'name' => 'ТЭН трубчатый 3000 Вт/220В крепление гайка латунная 1,5" L=250мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-3000vt-kreplenie-gayka-latunnaya-15-l250mm_HF',
            'required' => ['3000', '220', '1,5', '250'],
        ],
        [
            'name' => 'ТЭН трубчатый 3000 Вт/220В крепление гайка латунная 2" L=250мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-3000vt-kreplenie-gayka-latunnaya-2-l250mm',
            'required' => ['3000', '220', '2', '250'],
        ],
        [
            'name' => 'ТЭН трубчатый 4500 Вт/220В крепление гайка латунная 2" L=300мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-4500vt-kreplenie-gayka-latunnaya-2-l300mm',
            'required' => ['4500', '220', '2', '300'],
        ],
        [
            'name' => 'ТЭН трубчатый 6000 Вт/220В крепление гайка латунная 1,5" L=320мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-6000vt-kreplenie-gayka-latunnaya-15-l320mm',
            'required' => ['6000', '220', '1,5', '320'],
        ],
        [
            'name' => 'ТЭН трубчатый 6000 Вт/220В крепление гайка латунная 2" L=320мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-6000vt-kreplenie-gayka-latunnaya-2-l320mm',
            'required' => ['6000', '220', '2', '320'],
        ],
        [
            'name' => 'ТЭН трубчатый 7500 Вт/220В крепление гайка латунная 1,5" L=370мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-7500vt-kreplenie-gayka-latunnaya-15-l370mm',
            'required' => ['7500', '220', '1,5', '370'],
        ],
        [
            'name' => 'ТЭН трубчатый 7500 Вт/220В крепление гайка латунная 2" L=370мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-7500vt-kreplenie-gayka-latunnaya-2-l370mm',
            'required' => ['7500', '220', '2', '370'],
        ],
        [
            'name' => 'ТЭН трубчатый 12000 Вт/380В крепление гайка латунная 1,5" L=450мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-12000vt-kreplenie-gayka-latunnaya-15-l450mm',
            'required' => ['12000', '380', '1,5', '450'],
        ],
        [
            'name' => 'ТЭН трубчатый 15000 Вт/380В крепление гайка латунная 1,5" L=500мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-15000vt-kreplenie-gayka-latunnaya-15-l500mm',
            'required' => ['15000', '380', '1,5', '500'],
        ],
        [
            'name' => 'ТЭН трубчатый 15000 Вт/380В крепление гайка латунная 2" L=450мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-15000vt-kreplenie-gayka-latunnaya-2-l-450-mm',
            'required' => ['15000', '380', '2', '450'],
        ],
        [
            'name' => 'ТЭН 1,5 кВт, фланец, гнутый, под анод М6',
            'url' => 'https://tenko.ua/ru/catalog/good/ten-15-kvt-flanec-gnutyy-pod-anod-m6',
            'required' => ['1,5', '220', 'м6', 'гнут'],
        ],
        [
            'name' => 'ТЭН 1,5 кВт, гайка, гнутый, под анод М6',
            'url' => 'https://tenko.ua/ru/catalog/good/ten-15-kvt-gayka-gnutyy-pod-anod-m6',
            'required' => ['1,5', '220', 'м6', 'гнут'],
        ],
        [
            'name' => 'ТЭН 1,5 кВт, фланец, гнутый, длинная ножка, под анод М6',
            'url' => 'https://tenko.ua/ru/catalog/good/ten-15-kvt-flanec-gnutyy-dlinnaya-noghka-pod-anod-m6',
            'required' => ['1,5', '220', 'м6', 'гнут'],
        ],
        [
            'name' => 'ТЭН 1,5 кВт, гайка, прямой, нерж. под анод М6',
            'url' => 'https://tenko.ua/ru/catalog/good/15-kvt-nerghaveyka',
            'required' => ['1,5', '220', 'м6', 'прям'],
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 3_220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-3-220-15',
            'required' => ['3', '230', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 4,5_220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-45-220-15',
            'required' => ['4,5', '230', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 6_220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-6-220-15',
            'required' => ['6', '230', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 7,5_220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-75-220-15',
            'required' => ['7,5', '230', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 9_220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-9-380-15',
            'required' => ['9', '400', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 10,5 220/380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-105-380-15',
            'required' => ['10,5', '400', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'Блок нагревателей регулируемый БНР 1,5" 12_380',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-nagrevateley-reguliruemyy-bnr-12-380-15',
            'required' => ['12', '400', '1,5'],
            'images_only' => true,
        ],
        [
            'name' => 'ТЭН трубчатый 3 кВт/220В крепление гайка латунная 1 1/4"',
            'url' => 'https://tenko.by/katalog/komplektuyushhie/blok-tenov-iz-nerzhaveyushhej-stali-moshhnostyu-3,0-kvt/220v-dlya-kotlov-serii-mini-tm-tenko-%28prisoedinitelnaya-rezba-1-1/4-dyujma%29.html',
            'required' => ['3,0', '220', '1 1/4', '250'],
            'images_only' => true,
            // The following images on tenko.by are recommendation cards.
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 3000 Вт/220В крепление гайка латунная 1 1/4" L=250мм',
            'url' => 'https://tenko.by/katalog/komplektuyushhie/blok-tenov-iz-nerzhaveyushhej-stali-moshhnostyu-3,0-kvt/220v-dlya-kotlov-serii-mini-tm-tenko-%28prisoedinitelnaya-rezba-1-1/4-dyujma%29.html',
            'required' => ['3,0', '220', '1 1/4', '250'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        // These discontinued/non-standard variants no longer have exact cards in
        // the current Belarus catalog. Use only a verified official image of the
        // same TENKO construction family; never copy its specs or description.
        [
            'name' => 'ТЭН трубчатый 9000 Вт/220В крепление гайка латунная 1,5" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-9000vt-kreplenie-gayka-latunnaya-15-l420mm',
            'required' => ['9', '380', '1,5', '420'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 10500 Вт/380В крепление гайка латунная 1,5" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-10500vt-kreplenie-gayka-latunnaya-380v-2-l420mm',
            'required' => ['10,5', '380', '2', '420'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 10500 Вт/380В крепление гайка латунная 2" L=450мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-10500vt-kreplenie-gayka-latunnaya-380v-2-l420mm',
            'required' => ['10,5', '380', '2', '420'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 1,5 кВт/220В крепление гайка латунная 1 1/4"',
            'url' => 'https://tenko.ua/ru/catalog/good/15-kvt-nerghaveyka',
            'required' => ['1,5', '220', '1 1/4', '285'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 2 кВт/220В крепление гайка латунная 1 1/4"',
            'url' => 'https://tenko.ua/ru/catalog/good/2-kvt-nerghaveyka',
            'required' => ['2', '220', '1 1/4'],
            'images_only' => true,
            'image_limit' => 1,
        ],
        [
            'name' => 'ТЭН трубчатый 1,2 кВт/220В крепление гайка латунная 1/2" L=400мм',
            'url' => 'https://tenko.ua/ru/catalog/good/1200vt',
            'required' => ['1,2', '220', '1/2'],
            'images_only' => true,
            'image_limit' => 1,
        ],
    ];

    /** @var array<string,string> Exact Thermokraft names mapped to official STEN catalog images. */
    private const STEN_IMAGES = [
        'ТНБР-3-1 1/4 - ТН с терморегулятором (ТЭН нержавейка)' => 'img/products/sten/sten-tenbr-g114.jpg',
        'ТНБР-3-1 1/4 - Т с терморегулятором' => 'img/products/sten/sten-tenbr-g114.jpg',
        'ТНБР-4-1 1/4 - Т с терморегулятором' => 'img/products/sten/sten-tenbr-g114.jpg',
        'ТНБР-5-1 1/4 - Т с терморегулятором' => 'img/products/sten/sten-tenbr-g114.jpg',
        'ТЭНБ - 3 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-3.png',
        'ТЭНБ - 4,5 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-4-5.png',
        'ТЭНБ - 6 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-6.png',
        'ТЭНБ - 7,5 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-7-5.png',
        'ТЭНБ - 9 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-9.png',
        'ТЭНБ - 10,5 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-12.png',
        'ТЭНБ - 12 - G1½"- Ч с колпаком' => 'img/products/sten/sten-tenb-g112-12.png',
        'ТЭНБ - 3 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-3.png',
        'ТЭНБ - 4,5 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-4-5.png',
        'ТЭНБ - 6 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-6.png',
        'ТЭНБ - 7,5 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-7-5.png',
        'ТЭНБ - 9 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-9.png',
        'ТЭНБ - 10,5 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-12.png',
        'ТЭНБ - 12 - G1½"- НЧ с колпаком' => 'img/products/sten/sten-tenb-g112-12.png',
    ];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(300, (int) $this->option('sleep'));
        $stats = ['linked_cards' => 0, 'missing_images_before' => 0, 'missing_images_after' => 0, 'candidates' => 0, 'verified' => 0, 'enriched' => 0, 'images' => 0, 'specs' => 0, 'skipped' => 0, 'errors' => 0, 'sten_heater_cards' => 0, 'sten_missing_before' => 0, 'sten_candidates' => 0, 'sten_enriched' => 0, 'sten_missing_after' => 0];

        $rows = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('s.code', 'thermokraft')
            ->where('p.is_archived', false)
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('b.name', 'TENKO')
            ->select('sp.id as supplier_product_id', 'sp.source_url', 'p.id as product_id', 'p.name', 'p.images')
            ->get();

        $stats['linked_cards'] = $rows->count();
        $stats['missing_images_before'] = $rows->filter(fn ($row) => $this->hasNoImages($row->images))->count();

        $rows = $rows
            ->keyBy(fn ($row): string => $this->key((string) $row->name));

        foreach (self::VERIFIED as $rule) {
            if ($stats['verified'] >= $limit) {
                break;
            }

            $row = $rows->get($this->key($rule['name']));
            if (! $row) {
                $stats['skipped']++;

                continue;
            }
            if (preg_match('#^https?://(?:www\.)?tenko\.(?:ua|by)/#i', (string) $row->source_url) === 1 && ! $this->hasNoImages($row->images)) {
                $stats['skipped']++;

                continue;
            }

            $stats['candidates']++;
            try {
                $parsed = $enricher->preview($rule['url']);
                if (! $this->matchesOfficialCard((string) ($parsed['title'] ?? ''), $parsed['specs'] ?? [], $rule['required'])) {
                    $stats['errors']++;
                    $this->warn('Official card signature mismatch for product #'.(int) $row->product_id);

                    continue;
                }

                if (isset($rule['image_limit'])) {
                    $parsed['images'] = array_slice($parsed['images'] ?? [], 0, max(0, (int) $rule['image_limit']));
                }
                if (($parsed['images'] ?? []) === []) {
                    $stats['errors']++;
                    $this->warn('Official card has no usable images for product #'.(int) $row->product_id);

                    continue;
                }

                $stats['verified']++;
                $stats['images'] += count($parsed['images'] ?? []);
                $stats['specs'] += count($parsed['specs'] ?? []);

                if ($apply) {
                    $product = Product::query()->findOrFail((int) $row->product_id);
                    $imagesOnly = (bool) ($rule['images_only'] ?? false);
                    $result = $enricher->enrichFromParsed($product, $rule['url'], $parsed, [
                        'replace_images' => (bool) $this->option('overwrite-images'),
                        'update_images' => true,
                        'update_specs' => ! $imagesOnly,
                        'replace_specs' => ! $imagesOnly && (bool) $this->option('replace-specs'),
                        'min_specs_to_replace' => 4,
                        'update_service' => ! $imagesOnly,
                        'update_documents' => ! $imagesOnly,
                        'update_video' => ! $imagesOnly,
                        'update_content' => false,
                    ]);
                    DB::table('supplier_products')->where('id', (int) $row->supplier_product_id)->update([
                        'source_url' => $rule['url'],
                        'updated_at' => now(),
                    ]);
                    if (($result['updated_fields'] ?? []) !== []) {
                        $stats['enriched']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('Product #'.(int) $row->product_id.': '.$e->getMessage());
            }

            usleep($sleep * 1000);
        }

        $stats['missing_images_after'] = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get(['images'])
            ->filter(fn (Product $product) => $this->hasNoImages($product->images))
            ->count();

        $stenRows = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('s.code', 'thermokraft')
            ->where('p.is_archived', false)
            ->where('b.name', 'СТЭН')
            ->where(function ($query) {
                $query->where('p.name', 'like', 'ТЭН%')
                    ->orWhere('p.name', 'like', 'ТНБР%');
            })
            ->select('sp.id as supplier_product_id', 'p.id as product_id', 'p.name', 'p.images')
            ->get()
            ->keyBy(fn ($row): string => $this->key((string) $row->name));

        $stats['sten_heater_cards'] = $stenRows->count();
        $stats['sten_missing_before'] = $stenRows->filter(fn ($row) => $this->hasNoImages($row->images))->count();

        foreach (self::STEN_IMAGES as $name => $image) {
            $row = $stenRows->get($this->key($name));
            if (! $row || ! $this->hasNoImages($row->images)) {
                continue;
            }

            $stats['sten_candidates']++;
            if ($apply) {
                $product = Product::query()->findOrFail((int) $row->product_id);
                $product->images = [$image];
                $product->content_source_url = self::STEN_CATALOG_URL;
                $product->save();

                DB::table('supplier_products')->where('id', (int) $row->supplier_product_id)->update([
                    'source_url' => self::STEN_CATALOG_URL,
                    'updated_at' => now(),
                ]);
                $stats['sten_enriched']++;
            }
        }

        $stats['sten_missing_after'] = Product::query()
            ->whereIn('id', $stenRows->pluck('product_id'))
            ->get(['images'])
            ->filter(fn (Product $product) => $this->hasNoImages($product->images))
            ->count();

        foreach ($stats as $key => $value) {
            $this->line("metric.thermokraft_official.{$key}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<int,array<string,mixed>> $specs @param array<int,string> $required */
    private function matchesOfficialCard(string $title, array $specs, array $required): bool
    {
        $haystack = $this->key($title.' '.collect($specs)->map(fn ($spec) => ($spec['key'] ?? '').' '.($spec['value'] ?? ''))->implode(' '));

        foreach ($required as $value) {
            if (! str_contains($haystack, $this->key($value))) {
                return false;
            }
        }

        return $haystack !== '';
    }

    private function key(string $value): string
    {
        $value = mb_strtoupper(str_replace(['Ё', 'MM', 'KW'], ['Е', 'ММ', 'КВТ'], $value));

        return preg_replace('/[^A-ZА-Я0-9]+/u', '', $value) ?? '';
    }

    private function hasNoImages(mixed $images): bool
    {
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        return ! is_array($images) || $images === [];
    }
}
