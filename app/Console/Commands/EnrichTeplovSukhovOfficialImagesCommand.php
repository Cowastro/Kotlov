<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichTeplovSukhovOfficialImagesCommand extends Command
{
    protected $signature = 'supplier:enrich-teplov-sukhov-images
        {--limit=25 : Maximum number of exact matches to process}
        {--offset=0 : Skip exact matches before processing}
        {--apply : Download and save images; omitted means preview only}';

    protected $description = 'Add missing Teplov i Sukhov images only from exact official catalog matches.';

    /** @var list<string> */
    private const CATALOG_SECTIONS = [
        'https://teplov.ru/catalog/elementy_dymokhoda/adaptery/',
        'https://teplov.ru/catalog/elementy_dymokhoda/deflektor/',
        'https://teplov.ru/catalog/elementy_dymokhoda/zonty/',
        'https://teplov.ru/catalog/elementy_dymokhoda/zaglushki_revizii/',
        'https://teplov.ru/catalog/elementy_dymokhoda/komplektuyushchie_i_khomuty/',
        'https://teplov.ru/catalog/elementy_dymokhoda/kondesatootvod/',
        'https://teplov.ru/catalog/elementy_dymokhoda/konus/',
        'https://teplov.ru/catalog/elementy_dymokhoda/otvody/',
        'https://teplov.ru/catalog/elementy_dymokhoda/perekhody/',
        'https://teplov.ru/catalog/elementy_dymokhoda/ploshch_montazh/',
        'https://teplov.ru/catalog/elementy_dymokhoda/stenovye_krepleniya/',
        'https://teplov.ru/catalog/elementy_dymokhoda/troyniki_i_chetveriki/',
        'https://teplov.ru/catalog/elementy_dymokhoda/truby/',
        'https://teplov.ru/catalog/elementy_dymokhoda/shibery_i_zadvizhki/',
        'https://teplov.ru/catalog/dymokhodnye_sistemy/sistema_tis_ferrit_mc_black/',
        'https://teplov.ru/catalog/dymokhodnye_sistemy/sistema_tis_ferrit_1_mm/',
    ];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $brand = Brand::query()->where('name', 'Теплов и Сухов')->first();
        if (! $brand) {
            $this->error('Brand "Теплов и Сухов" not found.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $catalog = $this->officialCatalog();
        if ($catalog === []) {
            $this->error('The official catalog could not be loaded.');
            return self::FAILURE;
        }

        $this->line('Официальных позиций в индексе: ' . count($catalog));

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->whereRaw('JSON_LENGTH(images) = 0')
            ->orderBy('id')
            ->get();

        $exact = [];
        $ambiguous = 0;

        foreach ($products as $product) {
            $key = $this->key($product->name);
            $candidates = $catalog[$key] ?? [];

            if (count($candidates) === 1) {
                $exact[] = [$product, $candidates[0]];
            } elseif (count($candidates) > 1) {
                $ambiguous++;
            }
        }

        $selected = array_slice($exact, max(0, (int) $this->option('offset')), max(1, (int) $this->option('limit')));
        $stats = ['saved' => 0, 'failed' => 0];

        $this->info(sprintf(
            '%s: %d карточек без фото; %d точных совпадений; %d неоднозначных; обрабатываю %d.',
            $apply ? 'APPLY' : 'PREVIEW',
            $products->count(),
            count($exact),
            $ambiguous,
            count($selected),
        ));

        foreach ($selected as [$product, $candidate]) {
            $this->line(sprintf('#%d %s', $product->id, $product->name));
            $this->line('  ↳ ' . $candidate['url']);

            if (! $apply) {
                continue;
            }

            try {
                $result = $enricher->enrich($product, $candidate['url'], [
                    'update_images' => true,
                    'replace_images' => true,
                    'update_specs' => false,
                    'update_content' => false,
                ]);

                if (($result['images_saved'] ?? 0) > 0) {
                    SupplierProduct::query()
                        ->where('product_id', $product->id)
                        ->update(['source_url' => $candidate['url']]);
                    $stats['saved']++;
                    $this->line('  ✓ фото сохранено');
                } else {
                    $stats['failed']++;
                    $this->warn('  ! официальный источник не отдал пригодное изображение');
                }
            } catch (\Throwable $exception) {
                $stats['failed']++;
                $this->warn('  ! ' . $exception->getMessage());
            }
        }

        $this->newLine();
        $this->table(['Сохранено', 'Ошибки'], [[$stats['saved'], $stats['failed']]]);

        return self::SUCCESS;
    }

    /** @return array<string, list<array{name: string, url: string}>> */
    private function officialCatalog(): array
    {
        $index = [];

        foreach (self::CATALOG_SECTIONS as $sectionUrl) {
            try {
                $html = Http::timeout(25)->get($sectionUrl)->throw()->body();
            } catch (\Throwable $exception) {
                $this->warn('Не удалось загрузить: ' . $sectionUrl);
                continue;
            }

            preg_match_all('~<td\\b[^>]*itemscope[^>]*>.*?</td>~si', $html, $cards);
            foreach ($cards[0] ?? [] as $card) {
                if (! preg_match('~<meta\\s+itemprop=["\\\']name["\\\']\\s+content=["\\\']([^"\\\']+)["\\\']~si', $card, $nameMatch)
                    // The official catalogue uses a link tag, where href may precede
                    // itemprop. Match the attributes independently of their order.
                    || ! preg_match('~<link\\b(?=[^>]*\\bitemprop=["\\\']url["\\\'])(?=[^>]*\\bhref=["\\\']([^"\\\']+)["\\\'])[^>]*>~si', $card, $urlMatch)) {
                    continue;
                }

                $name = html_entity_decode(trim($nameMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = html_entity_decode(trim($urlMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! str_starts_with($url, 'http')) {
                    $url = 'https://teplov.ru/' . ltrim($url, '/');
                }

                $key = $this->key($name);
                if ($key !== '') {
                    $index[$key][] = ['name' => $name, 'url' => $url];
                }
            }
        }

        foreach ($index as $key => $candidates) {
            $index[$key] = array_values(array_unique($candidates, SORT_REGULAR));
        }

        return $index;
    }

    private function key(string $name): string
    {
        $name = html_entity_decode(mb_strtolower($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('~\\bтеплов\\s+и\\s+сухов\\b~u', ' ', $name) ?? $name;
        $name = str_replace(['ё', '°', '×', 'х'], ['е', '', 'x', 'x'], $name);
        // The official catalogue appends "М У" (mono, uninsulated) to
        // basic elements, while the imported price omits that suffix.
        $name = preg_replace('~\\s+м\\s+у\\s*$~u', '', $name) ?? $name;
        $name = preg_replace('~[^a-zа-я0-9]+~u', '', $name) ?? $name;

        return trim($name);
    }
}
