<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cards for the ТД «АТМОС» price rows (price.teplota.by) that have no
 * catalogue card yet: category from the price section and subsection, a
 * readable name, the model photo of the price plus the series gallery of
 * the atmos.by / teplota.by product page, specifications parsed from the
 * row description, retail = supplier RRP, «на складе» or «под заказ», and
 * the supplier link so supplier:sync-atmos keeps price and stock.
 * Descriptions are filled afterwards with product:enrich-content.
 *
 *   php artisan catalog:publish-atmos-cards                      # dry run
 *   php artisan catalog:publish-atmos-cards --apply --limit=200
 */
class PublishAtmosCardsCommand extends Command
{
    protected $signature = 'catalog:publish-atmos-cards
        {--apply : Create the cards and download their photos}
        {--limit=0 : Create at most N cards in this run (0 = all)}
        {--no-images : Do not download photos}';

    protected $description = 'Create catalogue cards for ТД «АТМОС» price rows that have none';

    /** Brand family => display name and country. */
    private const BRANDS = [
        'zota' => ['ZOTA', 'Россия'], 'atmos' => ['ATMOS', 'Чехия'], 'burnit' => ['BURNiT', 'Болгария'],
        'nordwest' => ['Норд-Вест', 'Россия'], 'drazice' => ['Drazice', 'Чехия'], 'tesy' => ['Tesy', 'Болгария'],
        'hansa' => ['HANSA', 'Германия'], 'regulus' => ['Regulus', 'Чехия'], 'altstream' => ['ALTStream', 'Россия'],
        'termica' => ['TERMICA', 'Китай'], 'moratop' => ['MORA-TOP', 'Чехия'], 'ondo' => ['ONDO', 'Россия'],
        'tebo' => ['TEBO', 'Китай'], 'radena' => ['Radena', 'Китай'], 'evolution' => ['Evolution', 'Китай'],
        'thermos' => ['THERMOS', 'Россия'], 'yasolar' => ['ЯSolar', 'Россия'],
    ];

    /** Ordered rules: [section ids, pattern on subsection + name, category slugs (first existing), name prefix]. */
    private const RULES = [
        [[1], '/^пеллетн\S* горелк/u', ['pelletnye-gorelki'], 'Пеллетная горелка'],
        [[1], '/шнек|транспортер|бункер/u', ['komplektuyushhie-dlya-otopleniya'], ''],
        [[1], '/laddomat|смесит/u', ['smesitelnye-klapany-i-uzly', 'smesitelnaya-armatura', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[1], '/^пеллетн/u', ['kotly-na-pelletah', 'tverdotoplivnye'], 'Пеллетный котел'],
        [[1], '/./u', ['tverdotoplivnye'], 'Твердотопливный котел'],
        [[2], '/./u', ['gazovye'], 'Газовый котел'],
        // Only real heating elements: «Комбинированные водонагреватели с керамическим ТЭНом» are water heaters.
        [[3, 5], '/^(тэн|нагревательн|блок.{0,3}тэн)|(?<!\p{L})esh(?!\p{L})/u', ['elektricheskie-teny-dlya-otopleniya', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[3], '/./u', ['elektricheskie'], 'Электрический котел'],
        [[5], '/косвен|ntr|okc|okh|vega|amet/u', ['kosvennye', 'vodonagrevateli'], 'Бойлер косвенного нагрева'],
        [[5], '/./u', ['vodonagrevateli', 'kosvennye'], 'Водонагреватель'],
        [[4], '/гидрострел|гидроразделит|гидравлическ/u', ['gidravlicheskie-razdeliteli-i-kollektory', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/гребенк|коллектор|fbh|hkv|mex|распредел/u', ['grebenki', 'gidravlicheskie-razdeliteli-i-kollektory', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/предохран|группа безопасности|воздухоотвод/u', ['predokhranitelnaya-i-reguliruyushchaya-armatura', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/смесит|tsv|термостатическ|насосн.. групп|клапан/u', ['smesitelnye-klapany-i-uzly', 'smesitelnaya-armatura', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/расширительн/u', ['membrannye-baki', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/труб|pex/u', ['truby-iz-sshitogo-polietilena', 'truby-i-fitingi', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/шкаф/u', ['kollektornye-shkafy', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4, 7], '/комплект.{0,4}подключ/u', ['komplekty-podklyucheniya', 'radiatornaya-armatura', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[4], '/./u', ['komplektuyushhie-dlya-otopleniya'], ''],
        [[6], '/./u', ['solnechnye-kollektory', 'komplektuyushhie-dlya-otopleniya'], 'Солнечный коллектор'],
        [[7], '/клапан|кран|термоголов/u', ['radiatornaya-armatura', 'komplektuyushhie-dlya-otopleniya'], ''],
        [[7], '/./u', ['radiatory', 'radiatory-otopleniya', 'bimetallicheskie-radiatory', 'komplektuyushhie-dlya-otopleniya'], 'Радиатор'],
        [[8], '/./u', ['tsirkulyatsionnyie', 'komplektuyushhie-dlya-otopleniya'], 'Циркуляционный насос'],
        [[9], '/./u', ['bufernye-emkosti', 'komplektuyushhie-dlya-otopleniya'], 'Буферная ёмкость'],
        [[10], '/./u', ['teplovyie-nasosyi'], 'Тепловой насос'],
        [[11], '/./u', ['teplonositeli'], 'Теплоноситель'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $sync = new SyncAtmosCommand;
        [$rows] = $sync->classify($sync->loadRows());
        $categories = Category::query()->where('is_active', true)->pluck('id', 'slug');
        $seen = [];
        $plan = [];
        $stats = ['rows' => count($rows), 'linked' => 0, 'no_price' => 0, 'no_category' => 0, 'duplicate' => 0, 'planned' => 0, 'created' => 0, 'images' => 0, 'errors' => 0];
        foreach ($rows as $row) {
            if ($row['product_id']) {
                $stats['linked']++;

                continue;
            }
            if ($row['retail'] === null) {
                $stats['no_price']++;

                continue;
            }
            if (isset($seen[$row['article_key']])) {
                $stats['duplicate']++;

                continue;
            }
            $seen[$row['article_key']] = true;
            [$categorySlugs, $prefix] = $this->rule($row);
            $categorySlug = collect($categorySlugs)->first(fn (string $slug): bool => isset($categories[$slug]));
            if (! $categorySlug) {
                $stats['no_category']++;
                $this->line(sprintf('audit.atmos_cards.item=%d:%s:%d:none', $row['section'], $row['family'] ?: 'other', $row['row']));

                continue;
            }
            $stats['planned']++;
            $plan[] = ['row' => $row, 'category' => $categorySlug, 'name' => $this->cardName($row, $prefix)];
            $this->line(sprintf('audit.atmos_cards.item=%d:%s:%d:%s', $row['section'], $row['family'] ?: 'other', $row['row'], $categorySlug));
        }

        if ($apply) {
            $supplier = Supplier::query()->where('code', SyncAtmosCommand::SUPPLIER_CODE)->first();
            if (! $supplier) {
                $this->error('Сначала запустите supplier:sync-atmos --apply: поставщик АТМОС не создан.');

                return self::FAILURE;
            }
            $created = [];
            foreach ($plan as $item) {
                if ($limit > 0 && $stats['created'] >= $limit) {
                    break;
                }
                try {
                    $product = $this->createCard($item, (int) $categories[$item['category']], $supplier, $stats);
                    $created[] = $product->id;
                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->warn($item['name'].': '.$e->getMessage());
                }
            }
            app(SupplierProductAvailabilityService::class)->refreshMany($created);
            Cache::forget('home:data:v6');
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.atmos_cards.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: карточки не созданы. Для записи добавьте --apply.');
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0: array<int, string>, 1: string} */
    public function rule(array $row): array
    {
        $text = mb_strtolower($row['subsection'].' '.$row['name']);
        foreach (self::RULES as [$sections, $pattern, $slugs, $prefix]) {
            if (in_array($row['section'], $sections, true) && preg_match($pattern, $text) === 1) {
                return [$slugs, $prefix];
            }
        }

        return [['komplektuyushhie-dlya-otopleniya'], ''];
    }

    /** «DC18S» → «Твердотопливный котел ATMOS DC18S»; «Гребенка ALTStream 2 выхода» keeps its words. */
    public function cardName(array $row, string $prefix): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $row['name']) ?? $row['name'], ' .,');
        $brand = self::BRANDS[$row['family']][0] ?? $row['brand'];
        $hasBrand = $row['family'] !== '' && (new SyncAtmosCommand)->family($name) === $row['family'];
        if (preg_match('/^\p{Cyrillic}/u', $name) === 1) {
            // Descriptive name: the brand goes before the first model code.
            return Str::limit($hasBrand ? $name : (preg_replace('/\s+(?=[A-Za-z0-9])/u', ' '.$brand.' ', $name, 1) ?? $name), 180, '');
        }
        if ($prefix === '' && preg_match('/^((?:\p{Cyrillic}+[\s-]*){1,4})/u', $row['subsection'], $type) === 1) {
            $prefix = trim($type[1], ' -');
        }

        $suffix = str_contains(mb_strtolower($row['subsection']), 'с пеллетной горелкой') ? ' с пеллетной горелкой' : '';

        return Str::limit(trim($prefix.' '.($hasBrand ? '' : $brand.' ').$name).$suffix, 180, '');
    }

    /** «Мощность 20 кВт. Длина дров - 30 см.» → [['Мощность', '20 кВт'], ['Длина дров', '30 см']]. */
    public function specs(array $row): array
    {
        $specs = [];
        foreach (preg_split('/(?<=[^\d])\.\s+|;\s*/u', $row['description']) ?: [] as $sentence) {
            if (preg_match('/^(\p{Lu}[\p{L}\s.()]{2,49}?)\s*[-:\x{2013}]?\s*(\d[^\n]{0,60})$/u', trim($sentence, " .\t"), $m)
                && preg_match('/цен|для |^до$/iu', $m[1]) !== 1) {
                $specs[] = ['key' => trim($m[1]), 'value' => trim($m[2], ' .')];
            }
        }
        [$brand, $country] = self::BRANDS[$row['family']] ?? [$row['brand'], null];
        $specs[] = ['key' => 'Бренд', 'value' => $brand];
        $specs[] = ['key' => 'Серия', 'value' => trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $row['subsection']) ?? $row['subsection'])];
        if ($row['article'] !== '') {
            $specs[] = ['key' => 'Артикул производителя', 'value' => $row['article']];
        }
        if ($country) {
            $specs[] = ['key' => 'Страна бренда', 'value' => $country];
        }

        return $specs;
    }

    private function createCard(array $item, int $categoryId, Supplier $supplier, array &$stats): Product
    {
        $row = $item['row'];
        $brand = $this->brand($row);
        $slug = $this->uniqueSlug($item['name']);
        $images = $this->option('no-images') ? [] : $this->images($row, $slug);
        $stats['images'] += count($images);
        $series = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $row['subsection']) ?? '');
        $short = Str::limit(trim($item['name'].'. '.($row['description'] ?: $series)), 250, '…');

        return DB::transaction(function () use ($item, $row, $brand, $slug, $images, $categoryId, $supplier, $series, $short): Product {
            $product = Product::query()->create([
                'category_id' => $categoryId,
                'brand_id' => $brand?->id,
                'name' => $item['name'],
                'h1' => $item['name'],
                'slug' => $slug,
                'sku' => $this->uniqueSku('ATMOS-'.$row['article_key']),
                'price' => $row['retail'],
                'currency' => 'BYN',
                'unit' => 'шт',
                'short_description' => $short,
                'content' => $this->content($item, $series),
                'images' => $images,
                'specs' => $this->specs($row),
                'service_info' => [
                    ['key' => 'Гарантия', 'value' => 'Гарантия производителя'],
                    ['key' => 'Поставка', 'value' => $row['in_stock'] ? 'Со склада в Минске' : 'Под заказ'],
                ],
                'content_source_url' => $row['url'],
                'meta_title' => Str::limit($item['name'], 60, '').' — купить в %city%',
                'meta_description' => Str::limit($short.' Цена, наличие и доставка по Беларуси.', 175, ''),
                'is_active' => true,
                'is_archived' => false,
                'is_new' => true,
                'in_stock' => $row['in_stock'],
                'availability_status' => $row['in_stock'] ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
                'requires_price_confirmation' => false,
            ]);
            SupplierProduct::query()->updateOrCreate([
                'supplier_id' => $supplier->id,
                'supplier_article_normalized' => $row['article_key'],
            ], [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'supplier_article' => $row['article'] !== '' ? $row['article'] : $row['article_key'],
                'supplier_article_compact' => $row['article_key'],
                'supplier_name' => trim($row['brand'].' '.$row['name']),
                'source_url' => $row['url'],
                'price' => $row['wholesale'] ?? $row['retail'],
                'price_byn' => $row['wholesale'] ?? $row['retail'],
                'currency' => 'BYN',
                'currency_rate' => 1,
                'in_stock' => $row['in_stock'],
                'stock_status' => $row['in_stock'] ? 'in_stock' : 'preorder',
                'stock_text' => $row['in_stock'] ? 'На складе' : 'Под заказ',
                'warehouse_name' => 'Склад АТМОС, Минск',
                'last_stock_synced_at' => now(),
                'match_status' => 'matched',
                'match_confidence' => 'created_from_live_price',
                'raw' => $row,
                'last_synced_at' => now(),
            ]);
            if ($brand) {
                $supplier->brands()->syncWithoutDetaching([$brand->id]);
            }

            return $product;
        });
    }

    private function content(array $item, string $series): string
    {
        $row = $item['row'];
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);
        $html = '<p>'.$e($item['name']).($series !== '' ? ' — модель серии '.$e($series) : '').'.</p>';
        $facts = array_filter(array_map('trim', preg_split('/\.\s+/u', $row['description']) ?: []));
        if ($facts !== []) {
            $html .= "\n<h3>Коротко о модели</h3>\n<ul>";
            foreach ($facts as $fact) {
                $html .= '<li>'.$e(rtrim($fact, '.')).'</li>';
            }
            $html .= '</ul>';
        }

        return $html."\n<p>Поставка от официального дистрибьютора в Беларуси. Поможем подобрать модель, комплектацию и монтаж; цена и наличие обновляются автоматически.</p>";
    }

    /** Model photo of the price first, then the series gallery of the product page. @return array<int, string> */
    private function images(array $row, string $slug): array
    {
        $urls = [];
        if ($row['image_id']) {
            $response = Http::asForm()->timeout(30)->post('https://price.teplota.by/ajax/show_image', ['good_id' => $row['image_id']]);
            if ($response->ok() && is_string($path = $response->json('Image')) && $path !== '') {
                $urls[] = 'https://price.teplota.by/'.ltrim($path, '/');
            }
        }
        if ($row['url']) {
            $page = Cache::remember('atmos-series:'.sha1($row['url']), 3600, fn () => (string) Http::timeout(40)->get($row['url'])->body());
            preg_match_all('~href="(https?://(?:www\.)?(?:atmos|teplota)\.by/img/misc/[^"]+\.(?:jpe?g|png|webp))"~i', $page, $gallery);
            $urls = array_merge($urls, array_slice(array_values(array_unique($gallery[1])), 0, 5));
        }
        $paths = [];
        foreach (array_values(array_unique($urls)) as $i => $url) {
            $body = Http::timeout(40)->get($url);
            if (! $body->ok() || strlen($body->body()) < 3000) {
                continue;
            }
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
            $path = 'products/atmos/'.$slug.'-'.($i + 1).'.'.$ext;
            Storage::disk('public')->put($path, $body->body());
            $paths[] = $path;
        }

        return $paths;
    }

    private function brand(array $row): ?Brand
    {
        if ($row['family'] === '') {
            return null;
        }
        $spellings = SyncAtmosCommand::BRAND_FAMILIES[$row['family']];
        $existing = Brand::query()->where(function ($q) use ($spellings) {
            foreach ($spellings as $spelling) {
                $q->orWhereRaw('LOWER(name) = ?', [$spelling])->orWhere('slug', Str::slug($spelling));
            }
        })->orderByDesc('is_active')->first();
        if ($existing) {
            return $existing;
        }
        [$name, $country] = self::BRANDS[$row['family']];

        return Brand::query()->create(['name' => $name, 'slug' => Str::slug($name) ?: $row['family'], 'h1' => $name, 'country' => $country, 'is_active' => true]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'atmos-'.Str::random(6);
        $slug = $base;
        $i = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uniqueSku(string $base): string
    {
        $base = Str::limit($base, 60, '');
        $sku = $base;
        $i = 2;
        while (Product::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
