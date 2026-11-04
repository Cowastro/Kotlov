<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Integrations\IntegrationProductMatchDecision;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\BaxiTeplodvorCatalog;
use App\Services\Suppliers\VistarPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the VISTAR rows no card of ours carries: the title by the kind of item («Газовый котел BAXI ECO Four
 * 24 F», «Бойлер косвенного нагрева Viena WTD 100»), the price is the retail one, the category is the one most
 * cards of the same series (else sheet) of VISTAR are in. Photos, specs and the description come from the
 * teplodvor.by page of the model when there is one; otherwise the card waits for a photo (hidden). A card is never
 * made where one of the same brand and model exists (archived ones too), nor for a row without a retail price.
 * The row of our 1C base with the same article joins the card.
 *
 *   php artisan supplier:vistar-create                       # plan
 *   php artisan supplier:vistar-create --limit=20 --apply    # create and fill
 */
class CreateVistarCardsCommand extends Command
{
    protected $signature = 'supplier:vistar-create
        {--only=* : Only these row codes (comma-separated or repeated)}
        {--except=* : Not these row codes (comma-separated or repeated)}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for VISTAR offers (BAXI, Viena, Reflex, Viessmann) with teplodvor.by photos';

    public function handle(BaxiTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncVistarCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.vistar_create.supplier=0');

            return self::FAILURE;
        }
        $except = collect((array) $this->option('except'))->flatMap(fn ($v) => explode(',', (string) $v))->map(fn ($v) => trim($v))->filter()->all();
        $only = collect((array) $this->option('only'))->flatMap(fn ($v) => explode(',', (string) $v))->map(fn ($v) => trim($v))->filter()->all();
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->where('stock_status', '!=', 'out_of_stock')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer) => ($only === [] || in_array($offer->supplier_article, $only, true)) && ! in_array($offer->supplier_article, $except, true))->values();
        $brands = DB::table('brands')->get(['id', 'name'])->mapWithKeys(fn ($b) => [mb_strtolower(Str::slug((string) $b->name, '')) => (int) $b->id]);
        $existing = [];
        foreach (DB::table('products')->whereIn('brand_id', $brands->values())->get(['name', 'brand_id']) as $card) {
            $existing[$card->brand_id.'|'.VistarPriceList::modelKey((string) $card->name)] = true;
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'no_retail' => 0, 'no_category' => 0, 'on_site' => 0, 'onec' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            // The offer keeps «BAXI LUNA Century 1.16»: the name of the row is without the brand.
            $raw['name'] ??= trim((string) preg_replace('/^'.preg_quote((string) ($raw['brand'] ?? ''), '/').'\s+/u', '', (string) $offer->supplier_name));
            $brandId = (int) ($brands[mb_strtolower(Str::slug((string) ($raw['brand'] ?? ''), ''))] ?? 0);
            $title = self::cardTitle($raw);
            $code = Str::slug((string) $offer->supplier_article);
            if ((float) ($raw['retail'] ?? 0) <= 0) {
                $stats['no_retail']++;
                $this->line("audit.vistar_create.no_retail={$code}");

                continue;
            }
            if ($brandId && isset($existing[$brandId.'|'.VistarPriceList::modelKey($title)])) {
                $stats['card_exists']++;
                $this->line("audit.vistar_create.card_exists={$code}");

                continue;
            }
            $categoryId = (int) ($categories[self::categorySlug($raw, $title)] ?? 0);
            if (! $categoryId) {
                $stats['no_category']++;
                $this->line("audit.vistar_create.no_category={$code}");

                continue;
            }
            $url = $site->find($title);
            $oneC = $this->oneCRow((string) ($raw['article'] ?? $offer->supplier_article));
            $stats['planned']++;
            $stats['on_site'] += (int) ($url !== null);
            $stats['onec'] += (int) ($oneC !== null);
            $this->line(sprintf('audit.vistar_create.offer=%s:%s:%s:%d:%s', $code, DB::table('categories')->where('id', $categoryId)->value('slug') ?: $categoryId, $url ? 'teplodvor' : 'price', (int) $oneC?->id, Str::slug(Str::limit($title, 80, ''))));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $product = $this->createCard($offer, $title, $categoryId, $brandId);
                $existing[$brandId.'|'.VistarPriceList::modelKey($title)] = true;
                $stats['created']++;
                if ($oneC && ! $oneC->product_id) {
                    app(IntegrationProductMatchDecision::class)->linkSelectedProduct($oneC, $product->id);
                }
                $page = $url ? $site->page($url) : null;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'https://www.baxi.ru/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    'description' => (string) ($page['desc'] ?? '') ?: trim($product->name.'. '.(string) ($raw['type'] ?? '')),
                ], [
                    'verified_images' => $images, 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
                    'update_content' => true, 'min_specs_for_ai' => 2, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if ($product->imagePaths() !== []) {
                    $product->forceFill(['is_active' => true, 'created_via' => null])->save();
                    $stats['with_photo']++;
                }
                app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);
                $this->line(sprintf('audit.vistar_create.created=%s:%d:%s', $code, $product->id, $product->slug));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        $stats['site_pages'] = $site->indexSize();
        foreach ($stats as $key => $value) {
            $this->line("metric.vistar_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** «ECO Four 24 F» → «Газовый котел BAXI ECO Four 24 F»; «WTD 100 (Мощность…) 470x470x1220 …» → «Бойлер косвенного нагрева Viena WTD 100». */
    public static function cardTitle(array $raw): string
    {
        $name = trim((string) preg_replace(['/\s*\((?:[^()]*подарок[^()]*)\)/u', '/\s+/u'], ['', ' '], (string) ($raw['name'] ?? '')));
        $brand = (string) ($raw['brand'] ?? '');
        $section = mb_strtolower((string) ($raw['section'] ?? ''));
        $type = mb_strtolower((string) ($raw['type'] ?? ''));
        $sheet = mb_strtolower((string) ($raw['sheet'] ?? ''));
        $isBoiler = preg_match('/котл/u', $section) && ! preg_match('/принадлежн|дымоход|комплект/u', $section) && preg_match('/^[A-Za-z]/u', $name);
        if (str_contains($name, 'Ampera')) {
            return 'Электрический котел '.$name;
        }
        if ($isBoiler) {
            $kind = match (true) {
                str_contains($section, 'конденсац') || str_contains($sheet, 'конденсац') => 'Конденсационный газовый котел',
                str_contains($section, 'напольн') => 'Напольный газовый котел',
                default => 'Газовый котел',
            };
            $circuits = str_contains($type, 'двух') ? ' (двухконтурный)' : (str_contains($type, 'одно') ? ' (одноконтурный)' : '');

            return trim($kind.' '.$brand.' '.$name).$circuits;
        }
        if ($brand === 'Viena' && preg_match('/^([A-Z]+\s*\d+[A-Z]?)/u', $name, $m)) {
            return 'Бойлер косвенного нагрева Viena '.$m[1];
        }
        if ($brand === 'Reflex' && preg_match('/^(\d+)\s*литр[а-я]*,\s*(\d+)\s*бар/u', $name, $m)) {
            $series = str_contains($section, 'питьев') ? 'DE' : 'N';

            return "Мембранный бак Reflex {$series} {$m[1]} л, {$m[2]} бар";
        }

        // The same «Коаксиальное колено 90° 60/100 мм» is an aluminium part and an aluminium-polypropylene one.
        if (str_contains($section, 'полипропилен') && ! preg_match('/полипропилен|конденсац/u', mb_strtolower($name))) {
            $name .= ' (алюминий/полипропилен)';
        }

        return $brand !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($brand)) ? trim($name.', '.$brand) : $name;
    }

    /** The category of a row by the kind of item: boilers, water heaters, tanks, flue parts, controls. */
    public static function categorySlug(array $raw, string $title): string
    {
        $text = mb_strtolower($title);
        $rules = [
            'elektricheskie' => '/ampera/u',
            'gazovye' => '/газовый котел|котёл vitodens|котел vitodens/u',
            'kosvennye' => '/бойлер косвенного/u',
            'zapchasti-dlya-kotlov' => '/комплект перевода|нейтрализац|наполнитель|трехходов|трёхходов|дымовой колпак/u',
            'regulyatory' => '/термостат|контроллер|регулятор|zont|модуль управления|каскадн|панель управления|vitotrol|аксессуар для управления|vitocom|удаленного управления/u',
            'gruppy-bystrogo-montazha-kotelnyx' => '/группа быстрого монтажа|насосная группа/u',
            'gidravlicheskie-razdeliteli-i-kollektory' => '/коллектор|крепеж для распределителя/u',
            'stabilizatory-napryazheniya' => '/инверторный стабилизатор/u',
            'membrannye-baki' => '/мембранный бак|расширительн[а-я]* бак|крепеж[а-я]* для расширительн/u',
            'predokhranitelnaya-i-reguliruyushchaya-armatura' => '/flowjet|клапан для расширительн/u',
            'datchiki' => '/датчик/u',
            'homuty-i-rastyazhki-dymohoda' => '/хомут/u',
            'rozety-i-dekorativnye-elementy-dymohoda' => '/накладка/u',
            'uplotneniya-vtulki-i-flantsy-dymohoda' => '/диафрагма/u',
            'krepleniya-dymohod' => '/распорка/u',
            'kondensatootvody' => '/конденсатосборник|конденсатоотвод|^ревизия/u',
            'zaglushki-dymohod' => '/^(наконечник|вертикальный оголовок|оголовок)/u',
            'perehody-adaptery-dymohod' => '/адаптер|переход|присоединительн/u',
            'koaxial-dymoxod' => '/коакс|дымоход|труба|колено|удлин|проход|тройник|отвод/u',
        ];
        foreach ($rules as $slug => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $slug;
            }
        }

        return '';
    }

    /** The one row of our 1C base with this article in its name or code. */
    private function oneCRow(string $article): ?IntegrationProduct
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($article)) ?? '';
        if (mb_strlen($compact) < 5) {
            return null;
        }
        $rows = IntegrationProduct::query()->where('integration_source_id', IntegrationSource::query()->where('code', 'onec')->value('id'))
            ->where(fn ($q) => $q->where('external_sku', $article)->orWhere('name', 'like', '%'.$article.'%'))->limit(2)->get();

        return $rows->count() === 1 ? $rows->first() : null;
    }

    private function createCard(SupplierProduct $offer, string $title, int $categoryId, int $brandId): Product
    {
        $raw = (array) $offer->raw;
        $sku = 'VISTAR-'.Str::upper(Str::limit(Str::slug((string) $offer->supplier_article), 60, ''));
        $specs = array_values(array_filter([
            ['key' => 'Артикул производителя', 'value' => (string) ($raw['article'] ?? '')],
            ['key' => 'Тип', 'value' => (string) ($raw['type'] ?? '')],
            ['key' => 'Производитель', 'value' => (string) ($raw['brand'] ?? '')],
        ], fn (array $spec) => $spec['value'] !== ''));
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId ?: null,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'short_description' => Str::limit($title.'. Официальная поставка в Беларусь, гарантия производителя.', 250, ''),
            'images' => [],
            'specs' => $specs,
            'unit' => 'шт',
            'is_active' => false,
            'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS,
            'is_archived' => false,
            'is_new' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'meta_title' => Str::limit($title, 60, '').' купить в %city%',
            'meta_description' => Str::limit($title.' — цена, наличие и доставка по Беларуси.', 175, ''),
        ]);
        $offer->forceFill(['product_id' => $product->id, 'product_sku' => $sku, 'match_status' => 'matched', 'match_confidence' => 'created_from_price'])->save();

        return $product;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
