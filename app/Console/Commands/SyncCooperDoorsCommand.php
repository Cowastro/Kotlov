<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\CooperWholesalePriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ООО «СаунаОпт»: glass sauna doors Cooper (cooper.by, series Лайт, Стандарт, PRO, PREMIUM).
 * Rows come from the shop's WooCommerce Store API. Until the wholesale price list arrives
 * the site price is the card's retail price. The cost comes from the wholesale price list of the
 * supplier uploaded in the admin (CooperWholesalePriceList, series + colour + size); where its recommended
 * retail differs from the site, the line audit.cooper_doors.rrc_differs says so — the site stays the retail.
 *
 *   php artisan supplier:sync-cooper-doors                         # dry run
 *   php artisan supplier:sync-cooper-doors --apply --create-new    # first import: cards, photos, specs
 *   php artisan supplier:sync-cooper-doors --apply                 # daily: retail price and stock
 */
class SyncCooperDoorsCommand extends Command
{
    protected $signature = 'supplier:sync-cooper-doors
        {--apply : Write links, prices and stock}
        {--create-new : Create cards for doors that have none}
        {--refresh-images : Reload the photos of cards that have fewer than the site}';

    protected $description = 'Sync Cooper sauna doors of ООО «СаунаОпт» from cooper.by';

    public const SUPPLIER_CODE = 'saunaopt';

    private const SUPPLIER_NAME = 'ООО «СаунаОпт»';

    private const SITE = 'https://cooper.by';

    /** WooCommerce category «Двери» and its series. */
    private const DOORS_CATEGORY = 176;

    private const CATEGORY_SLUG = 'dveri-dlya-ban-i-saun';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = $this->rows();
        if ($rows === []) {
            $this->line('metric.cooper_doors.rows=0');
            $this->error('cooper.by returned no doors.');

            return self::FAILURE;
        }

        $stats = ['rows' => count($rows), 'no_price' => 0, 'linked' => 0, 'created' => 0, 'would_create' => 0, 'retail_updated' => 0, 'images_saved' => 0, 'in_stock' => 0,
            'with_cost' => 0, 'no_cost' => 0, 'rrc_differs' => 0, 'errors' => 0];
        $wholesale = CooperWholesalePriceList::load();
        $seenKeys = [];
        $supplier = $apply ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $sync = $apply ? $this->sync($supplier) : null;
        $touched = [];

        foreach ($rows as $row) {
            if ($row['retail'] === null) {
                $stats['no_price']++;
                $this->line('audit.cooper_doors.no_price='.$row['id']);

                continue;
            }
            $stats['in_stock'] += (int) $row['in_stock'];
            try {
                $link = $supplier ? SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $row['article'])->first() : null;
                $product = $link?->product_id ? Product::query()->find($link->product_id) : null;
                if (! $product) {
                    if (! $this->option('create-new')) {
                        $this->line('audit.cooper_doors.unlinked='.$row['id']);

                        continue;
                    }
                    if (! $apply) {
                        $stats['would_create']++;
                        $this->line(sprintf('audit.cooper_doors.create=%d:%s:%d', $row['id'], Str::slug($row['title']), (int) $row['retail']));

                        continue;
                    }
                    $product = $this->createCard($row);
                    $stats['created']++;
                }
                $stats['linked']++;
                // The site names the series only in the category («Серия Стандарт»), not in the title.
                $key = self::doorKey($row['series'].' '.$row['site_name']);
                $seenKeys[$key] = true;
                $offer = $wholesale[$key] ?? null;
                if ($offer) {
                    $stats['with_cost']++;
                    if (abs($offer['rrc'] - (float) $row['retail']) > 0.5) {
                        $stats['rrc_differs']++;
                        $this->line(sprintf('audit.cooper_doors.rrc_differs=%d:%d:%d', $row['id'], (int) round($row['retail']), (int) round($offer['rrc'])));
                    }
                } else {
                    $stats['no_cost']++;
                    $this->line('audit.cooper_doors.no_cost='.$row['id']);
                }
                if (! $apply) {
                    continue;
                }
                $refresh = (bool) $this->option('refresh-images') && count((array) $product->images) < count($row['images']);
                if ((empty($product->images) || $refresh) && $row['images'] !== []) {
                    // The shop's own photos of this door: «изображение_viber_….jpg» would fail the generic
                    // image filter (it drops messenger icons), so they go in as verified product photos.
                    $stats['images_saved'] += (int) (app(ProductSourceEnricher::class)->enrichFromParsed($product, $row['url'], ['images' => []], [
                        'verified_images' => $row['images'],
                        'update_images' => true, 'replace_images' => $refresh, 'update_specs' => false, 'update_service' => false, 'update_documents' => false,
                        'update_video' => false, 'update_content' => false,
                    ])['images_saved'] ?? 0);
                }

                SupplierProduct::query()->updateOrCreate([
                    'supplier_id' => $supplier->id,
                    'supplier_article' => $row['article'],
                ], [
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article_normalized' => Str::upper($row['article']),
                    'supplier_article_compact' => Str::upper($row['article']),
                    'supplier_name' => $row['site_name'],
                    'source_url' => $row['url'],
                    // The cost from the wholesale price list (with VAT); the retail is the site price.
                    'price' => $offer['wholesale'] ?? null,
                    'price_byn' => $offer['wholesale'] ?? null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $row['in_stock'],
                    'stock_quantity' => null,
                    'stock_status' => $row['in_stock'] ? 'in_stock' : 'preorder',
                    'stock_text' => $row['in_stock'] ? 'В наличии на cooper.by' : 'Под заказ',
                    'last_stock_synced_at' => now(),
                    'match_status' => 'matched',
                    'match_confidence' => 'site_product_id',
                    'raw' => ['retail' => $row['retail'], 'series' => $row['series'], 'woo_id' => $row['id'],
                        'wholesale_rrc' => $offer['rrc'] ?? null],
                    'last_synced_at' => now(),
                ]);
                $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                if (! $otherSupplier && (abs((float) $product->price - $row['retail']) > 0.005 || $product->requires_price_confirmation)) {
                    $product->forceFill(['price' => $row['retail'], 'requires_price_confirmation' => false])->save();
                    $stats['retail_updated']++;
                }
                if ($product->brand_id) {
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                }
                $touched[] = (int) $product->id;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($row['site_name'].': '.$e->getMessage());
            }
        }

        foreach (array_diff_key($wholesale, $seenKeys) as $key => $offer) {
            $this->line('audit.cooper_doors.price_list_only='.Str::slug($key));
        }

        if ($apply) {
            app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
            $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.cooper_doors.{$key}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One door, whatever the wording: «БДК "COOPER" Стандарт, бронза матовая, 190х70 ВР» and the site's
     * «Дверь стеклянная для бани COOPER Стандарт 190×70 бронза матовая ВР» give «standart|bronze-matte|190x70|vr».
     */
    public static function doorKey(string $name): string
    {
        $text = mb_strtolower(str_replace(['×', 'x', '*'], 'х', $name));
        $series = match (true) {
            str_contains($text, 'premium') || str_contains($text, 'премиум') => 'premium',
            (bool) preg_match('/\bpro\b|\bпро\b/u', $text) => 'pro',
            str_contains($text, 'стандарт') || str_contains($text, 'standart') => 'standart',
            str_contains($text, 'лайт') || str_contains($text, 'lite') => 'lite',
            default => '',
        };
        $colour = (str_contains($text, 'графит') ? 'graphite' : 'bronze').(str_contains($text, 'матов') ? '-matte' : '');
        $size = preg_match('/(\d{3})\s*х\s*(\d{2}(?:[.,]\d)?)/u', $text, $m) ? $m[1].'x'.str_replace(',', '.', $m[2]) : '';
        $vertical = preg_match('/(?<!\pL)вр(?!\pL)|вертикал/u', $text) ? 'vr' : '';

        return implode('|', array_filter([$series, $colour, $size, $vertical]));
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(): array
    {
        $rows = [];
        for ($page = 1; $page <= 5; $page++) {
            $items = Http::timeout(60)->withUserAgent('Mozilla/5.0 (compatible; KotlovBot)')
                ->get(self::SITE.'/wp-json/wc/store/v1/products', ['category' => self::DOORS_CATEGORY, 'per_page' => 100, 'page' => $page])
                ->throw()->json();
            foreach ((array) $items as $item) {
                $rows[] = self::row($item);
            }
            if (count((array) $items) < 100) {
                break;
            }
        }

        return $rows;
    }

    /** @param  array<string,mixed>  $item  a Store API product */
    public static function row(array $item): array
    {
        $siteName = html_entity_decode((string) $item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $minor = (int) ($item['prices']['currency_minor_unit'] ?? 2);
        $price = (int) ($item['prices']['price'] ?? 0) / 10 ** $minor;
        $series = collect($item['categories'] ?? [])->map(fn ($category) => html_entity_decode((string) $category['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->map(fn (string $name) => trim((string) preg_replace('/^серия\s+/iu', '', $name)))
            ->first(fn (string $name) => $name !== '' && mb_stripos($name, 'двер') === false) ?? '';

        $specs = [];
        $title = 'Дверь стеклянная для бани Cooper';
        if (preg_match('/(\d{3})\s*\*\s*(\d{2,3}(?:,\d)?)\s+(.+)$/u', $siteName, $m)) {
            [$height, $width, $colour] = [$m[1], str_replace(',', '.', $m[2]), trim($m[3])];
            $title .= ($series !== '' ? ' '.$series : '').' '.$height.'×'.str_replace('.', ',', $width).', '.mb_strtolower($colour);
            $specs = [
                'Серия' => $series,
                'Размер (В×Ш)' => $height.'×'.str_replace('.', ',', $width).' см',
                'Высота' => $height.' см',
                'Ширина' => str_replace('.', ',', $width).' см',
                'Цвет стекла' => Str::ucfirst(trim((string) preg_replace('/\s*матов\w*$/u', '', mb_strtolower($colour)))),
                'Поверхность стекла' => mb_stripos($colour, 'матов') !== false ? 'Матовая' : 'Глянцевая',
            ];
        } else {
            $title = $siteName;
        }
        $specs += self::kitSpecs((string) ($item['short_description'] ?? ''));

        return [
            'id' => (int) $item['id'],
            'article' => 'cooper-'.(int) $item['id'],
            'site_name' => $siteName,
            'title' => $title,
            'series' => $series,
            'url' => (string) $item['permalink'],
            'retail' => $price > 0 ? round($price, 2) : null,
            'in_stock' => (bool) ($item['is_in_stock'] ?? false),
            'images' => collect($item['images'] ?? [])->pluck('src')->filter()->values()->all(),
            'specs' => array_filter($specs, fn ($value) => $value !== ''),
            'short' => self::kitText((string) ($item['short_description'] ?? '')),
        ];
    }

    /** «Дверная коробка (40*75мм) — ОЛЬХА; Закаленное стекло — 8 мм; …» and the Лайт style «Стекло 8 мм; 3 петли; Коробка ОСИНА». */
    public static function kitSpecs(string $html): array
    {
        $specs = [];
        foreach (self::kitLines($html) as $line) {
            if (str_contains($line, '—')) {
                [$key, $value] = array_map('trim', explode('—', $line, 2));
                $key = (string) preg_replace('/\s*\(([^)]*)\)/u', '', $key);
                if (preg_match('/коробк/iu', $key) && preg_match('/\(([\d*×x ]+мм)\)/u', $line, $size)) {
                    $specs['Сечение коробки'] = preg_replace('/(\d)мм/u', '$1 мм', str_replace('*', '×', $size[1]));
                }
                $value = Str::lcfirst(rtrim($value, '.'));
                // «Ручка деревянная с магнитами — 2 шт», «Петли усиленные G60 — 3 шт»: the noun names the spec.
                if (preg_match('/^(ручка|петли)\s+(.+)$/iu', $key, $noun)) {
                    [$key, $value] = [$noun[1], $noun[2].', '.$value];
                }
                $specs[match (true) {
                    (bool) preg_match('/коробк/iu', $key) => 'Материал коробки',
                    (bool) preg_match('/стекл/iu', $key) => 'Стекло',
                    default => Str::ucfirst(mb_strtolower($key)),
                }] = Str::ucfirst(mb_strtolower(mb_substr($value, 0, 1)).mb_substr($value, 1));
            } elseif (preg_match('/^стекло\s+(\d+\s*мм)/iu', $line, $m)) {
                $specs['Стекло'] = 'Закалённое, '.$m[1];
            } elseif (preg_match('/^(\d+)\s+петл/iu', $line, $m)) {
                $specs['Петли'] = $m[1].' шт';
            } elseif (preg_match('/^коробка\s+(.+)$/iu', $line, $m)) {
                $specs['Материал коробки'] = Str::ucfirst(mb_strtolower($m[1]));
            } elseif (preg_match('/магнитн/iu', $line)) {
                $specs['Закрывание'] = 'Магнитное';
            } elseif (preg_match('/^(.+)\s+ручка$/iu', $line, $m)) {
                $specs['Ручка'] = Str::ucfirst(mb_strtolower($m[1]));
            }
        }
        // «Закаленное стекло — 8 мм» says the glass: keep it next to its thickness.
        if (isset($specs['Стекло']) && preg_match('/^\d/u', $specs['Стекло'])) {
            $specs['Стекло'] = 'Закалённое, '.$specs['Стекло'];
        }

        return $specs;
    }

    /** @return array<int,string> */
    private static function kitLines(string $html): array
    {
        $text = html_entity_decode(strip_tags(str_replace(['</li>', '<br>', '<br />', '</p>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(array_map(
            // «ОЛЬХА», «ОСИНА» are shouted on the site; Latin marks like «G60» stay.
            fn (string $line) => trim((string) preg_replace_callback('/(?<!\pL)\p{Cyrillic}{3,}(?!\pL)/u',
                fn (array $word) => mb_strtoupper($word[0]) === $word[0] ? mb_strtolower($word[0]) : $word[0],
                (string) preg_replace('/\s+/u', ' ', $line)), " ;.\t\n"),
            preg_split('/[;\n]+/u', $text) ?: []
        ), fn (string $line) => $line !== '' && mb_strlen($line) < 120));
    }

    private static function kitText(string $html): string
    {
        $lines = self::kitLines($html);

        return $lines === [] ? '' : 'Комплектация: '.implode('; ', array_map(fn (string $line) => Str::lcfirst($line), $lines)).'.';
    }

    private function createCard(array $row): Product
    {
        $brand = Brand::query()->whereRaw('LOWER(name) = ?', ['cooper'])->first()
            ?? Brand::query()->create(['name' => 'Cooper', 'slug' => Brand::query()->where('slug', 'cooper')->exists() ? 'cooper-by' : 'cooper', 'h1' => 'Cooper', 'country' => 'Беларусь', 'is_active' => true]);
        $category = Category::query()->where('slug', self::CATEGORY_SLUG)->firstOrFail();
        $slug = $base = Str::slug($row['title']);
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $short = trim($row['title'].'. '.$row['short']);

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $row['title'],
            'h1' => $row['title'],
            'slug' => $slug,
            'sku' => 'COOPER-'.$row['id'],
            'price' => $row['retail'],
            'currency' => 'BYN',
            'unit' => 'шт',
            'short_description' => Str::limit($short, 300, '…'),
            'specs' => collect($row['specs'])->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all(),
            'service_info' => [
                ['key' => 'Производитель', 'value' => 'Cooper, Беларусь'],
                ['key' => 'Открывание', 'value' => 'Универсальное: дверь навешивается на левую или правую сторону'],
            ],
            'content_source_url' => $row['url'],
            'meta_title' => Str::limit($row['title'], 60, '').' — купить в %city%',
            'meta_description' => Str::limit($short.' Цена, наличие и доставка по Беларуси.', 175, ''),
            'is_active' => true,
            'is_archived' => false,
            'is_new' => true,
            'in_stock' => $row['in_stock'],
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->updateOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => self::SITE,
            'communication_channels' => [['type' => 'site', 'value' => self::SITE]],
            'notes' => 'Двери для бани и сауны Cooper (серии Лайт, Стандарт, PRO, PREMIUM). Пока только розничные цены с сайта; оптовый прайс ожидается.',
            'is_active' => true,
        ]);
    }

    private function sync(Supplier $supplier): SupplierSync
    {
        return SupplierSync::query()->updateOrCreate(['key' => 'saunaopt_cooper_doors'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'СаунаОпт: двери Cooper',
            'description' => 'Розничные цены и наличие дверей Cooper с cooper.by.',
            'command' => 'supplier:sync-cooper-doors',
            'source_url' => self::SITE.'/product-category/dveri/',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
    }
}
