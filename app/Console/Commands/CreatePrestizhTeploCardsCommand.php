<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Integrations\IntegrationProductMatchDecision;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\PrestizhTeploPriceList;
use App\Services\Suppliers\PrestizhTeploTeplodvorCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the equipment rows of «Престиж Тепло» no card of ours carries: the title by the kind of item from the
 * price-list section («Газовый котел Vaillant atmoTEC pro VUW 240/5-3», «Расширительный бак Reflex N 8»), the retail
 * price of the list, the category by the title. Photos, specs and the description come from the teplodvor.by page
 * of the model; otherwise the card waits hidden for a photo. Fittings (Comisa, Conex Banninger, Walraven) and rows
 * without a brand stay offers for the КП form. A card is never made where one of the same brand and model exists
 * (archived ones too). The row of our 1C base with the same article joins the card.
 *
 *   php artisan supplier:prestizh-teplo-create                       # plan
 *   php artisan supplier:prestizh-teplo-create --limit=50 --apply    # create and fill
 */
class CreatePrestizhTeploCardsCommand extends Command
{
    protected $signature = 'supplier:prestizh-teplo-create
        {--only=* : Only these row codes (comma-separated or repeated)}
        {--brand=* : Only these brands}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for the equipment of Престиж Тепло missing on the site';

    /** Brands whose rows stay offers for the КП form only. */
    private const OFFERS_ONLY = ['comisa', 'conexbanninger', 'walraven', ''];

    /** Title words → category slug, the first that matches. */
    private const CATEGORIES = [
        '/^электрический котел/u' => 'elektricheskie',
        '/^(конденсационный газовый|газовый) котел/u' => 'gazovye',
        '/^электрический водонагреватель/u' => 'electric',
        '/^бойлер/u' => 'kosvennye',
        '/^буферная/u' => 'bufernye-emkosti',
        '/^расширительный бак|^мембранный бак/u' => 'membrannye-baki',
        '/^(насосная группа|насосно-смесительн|насосный модуль)/u' => 'gruppy-bystrogo-montazha-kotelnyx',
        '/гидравлическ\S* разделител|гидрострел/u' => 'gidravlicheskie-razdeliteli-i-kollektory',
        '/коллектор|распределительный модуль/u' => 'gidravlicheskie-razdeliteli-i-kollektory',
        '/^тэн|нагревательн\S* элемент|блок тэн/u' => 'elektricheskie-teny-dlya-otopleniya',
        '/^сепаратор/u' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        '/^(термо)?манометр|^термометр/u' => 'manometry-i-termometry',
        '/фильтр/u' => 'filtry',
        '/apamet stil/u' => 'electric',
        '/модуль wi-fi|модуль расширения|модуль (дистанционного|удаленного)|расширительный модуль|коммутационный модуль|мультифункциональный модуль|^модуль vr/u' => 'regulyatory',
        '/воронк|^трубка|присоединение циркуляц/u' => 'zapchasti-dlya-kotlov',
        '/прокладк/u' => 'uplotnitelnye-materialy',
        '/футорк|накидная гайка/u' => 'rezbovye-fitingi',
        '/арматура для наполнения/u' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        '/датчик/u' => 'datchiki',
        '/регулятор|термостат|автоматик|sensocomfort|calormatic|vrc|привод|контроллер|модуль управления/u' => 'regulyatory',
        '/смесит|трехходов|трёхходов/u' => 'smesitelnye-klapany-i-uzly',
        '/воздухоотвод|предохранит|клапан|сепаратор|группа безопасности|редуктор/u' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        '/дымоход|отвод|удлинени|адаптер|оголовок|проход|концентрическ|колено|60\/100|80\/125|dn 80/u' => 'koaxial-dymoxod',
        '/^насос/u' => 'tsirkulyatsionnyie',
        '/кран/u' => 'krany-i-zapornaya-armatura',
        '/гидроблок|кабельн\S* жгут|плата|переналадк|вентилятор|горелк/u' => 'zapchasti-dlya-kotlov',
        '/для баков|крепеж для бак/u' => 'membrannye-baki',
        '/анод|изоляция|крепление|крепеж|подключени|теплообменник/u' => 'komplektuyushchie-dlya-vodonagrevateley',
    ];

    public function handle(PrestizhTeploTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncPrestizhTeploCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.prestizh_create.supplier=0');

            return self::FAILURE;
        }
        $list = fn (string $option) => collect((array) $this->option($option))->flatMap(fn ($v) => explode(',', (string) $v))->map(fn ($v) => trim($v))->filter()->all();
        $only = $list('only');
        $onlyBrands = array_map(fn ($b) => Str::slug($b, ''), $list('brand'));
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->orderBy('id')->get()
            ->filter(function (SupplierProduct $offer) use ($only, $onlyBrands): bool {
                $brand = Str::slug(self::brandOf((array) $offer->raw), '');

                return ! in_array($brand, self::OFFERS_ONLY, true) && empty($offer->raw['no_auto_link'])
                    && ($only === [] || in_array($offer->supplier_article, $only, true))
                    && ($onlyBrands === [] || in_array($brand, $onlyBrands, true));
            })->values();
        $brands = DB::table('brands')->get(['id', 'name'])->mapWithKeys(fn ($b) => [Str::slug((string) $b->name, '') => (int) $b->id]);
        $existing = [];
        foreach (DB::table('products')->whereNotNull('brand_id')->get(['name', 'brand_id']) as $card) {
            $existing[$card->brand_id.'|'.PrestizhTeploPriceList::modelKey((string) $card->name)] = true;
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'no_category' => 0, 'on_site' => 0, 'onec' => 0,
            'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $brandName = self::brandOf($raw);
            $title = self::cardTitle($raw);
            $code = Str::slug((string) $offer->supplier_article);
            $brandId = (int) ($brands[Str::slug($brandName, '')] ?? 0);
            if ($brandId && isset($existing[$brandId.'|'.PrestizhTeploPriceList::modelKey($title)])) {
                $stats['card_exists']++;

                continue;
            }
            $categoryId = (int) ($categories[self::categorySlug($title)] ?? 0);
            if (! $categoryId) {
                $stats['no_category']++;
                $this->line('audit.prestizh_create.no_category='.$code.':'.Str::slug(Str::limit($title, 70, '')));

                continue;
            }
            $url = $site->find($title);
            $oneC = $this->oneCRow((string) ($raw['article'] ?? ''));
            $stats['planned']++;
            $stats['on_site'] += (int) ($url !== null);
            $stats['onec'] += (int) ($oneC !== null);
            $this->line(sprintf('audit.prestizh_create.offer=%s:%s:%s:%d:%s', $code, self::categorySlug($title), $url ? 'teplodvor' : 'price', (int) $oneC?->id, Str::slug(Str::limit($title, 80, ''))));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $brandId = $brandId ?: (int) Brand::query()->firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName, 'is_active' => true])->id;
                $brands[Str::slug($brandName, '')] = $brandId;
                $product = $this->createCard($offer, $title, $categoryId, $brandId, $brandName);
                $existing[$brandId.'|'.PrestizhTeploPriceList::modelKey($title)] = true;
                $stats['created']++;
                if ($oneC && ! $oneC->product_id) {
                    app(IntegrationProductMatchDecision::class)->linkSelectedProduct($oneC, $product->id);
                }
                $page = $url ? $site->page($url) : null;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'https://www.teplodvor.by/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    'description' => (string) ($page['desc'] ?? '') ?: trim($title.'. '.(string) ($raw['description'] ?? '').' '.(string) ($raw['section'] ?? '')),
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
                $this->line(sprintf('audit.prestizh_create.created=%s:%d:%s', $code, $product->id, $product->slug));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        $stats['site_pages'] = $site->indexSize();
        foreach ($stats as $key => $value) {
            $this->line("metric.prestizh_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** The brand of a row; the Apamet water heaters stand on the Galmet sheet. */
    public static function brandOf(array $raw): string
    {
        if (preg_match('/apamet/iu', (string) ($raw['section'] ?? ''))) {
            return 'Apamet';
        }

        return trim((string) ($raw['brand'] ?? ''));
    }

    /** «atmoTEC pro VUW 240/5-3» under «Настенные газовые котлы atmoTEC…» → «Газовый котел Vaillant atmoTEC pro VUW 240/5-3». */
    public static function cardTitle(array $raw): string
    {
        $brand = self::brandOf($raw);
        $name = trim((string) preg_replace(['/\s+/u', '/[.\s]+$/u'], [' ', ''], (string) ($raw['name'] ?? '')));
        // «Принадлежности к газовым котлам погодозависимая автоматика…»: the item, not the section.
        if (preg_match('/^принадлежности к \S+ котлам\s+(.+)$/iu', $name, $m)) {
            $name = mb_strtoupper(mb_substr($m[1], 0, 1)).mb_substr($m[1], 1);
        }
        // «OKC 100 NTR мощность теплообменника 24кВт»: the model, the rest is in the specs.
        $name = trim((string) preg_replace('/\s+(мощность|один|два)\s.*$/u', '', $name));
        $section = mb_strtolower((string) ($raw['section'] ?? '').' '.(string) ($raw['sheet'] ?? ''));
        // A name in words carries its kind («Изоляция для AQ PT 500»); a model takes it from the section.
        // A model may be Cyrillic too: «Гепард 23 MTV», «Пантера 25 KOO».
        // A flue terminal or an inhibitor in a boiler section is no boiler.
        $notAnItem = (bool) preg_match('/terminal|60\/100|80\/125|inhibitor|ингибитор/iu', $name);
        $type = $notAnItem || (preg_match('/^\p{Cyrillic}/u', $name) && ! preg_match('/^\p{Lu}\p{Ll}+\s+\d/u', $name)) ? '' : match (true) {
            (bool) preg_match('/нагревательн\S* элемент|тэн/u', $section) && ! preg_match('/^тэн/iu', $name) => 'ТЭН',
            str_contains($section, 'электрические водонагреватели') => 'Электрический водонагреватель',
            (bool) preg_match('/электрокотл|электрическ\S* котл/u', $section) => 'Электрический котел',
            (bool) preg_match('/бойлер/u', $section) && (bool) preg_match('/комбинированного нагрева/u', $section) => 'Бойлер комбинированного нагрева',
            (bool) preg_match('/бойлер/u', $section) => 'Бойлер косвенного нагрева',
            (bool) preg_match('/конденсац\S* .*котл/u', $section) => 'Конденсационный газовый котел',
            (bool) preg_match('/газов\S* котл/u', $section) && ! preg_match('/принадлежн/u', $section) => 'Газовый котел',
            (bool) preg_match('/буфер/u', $section) => 'Буферная ёмкость',
            (bool) preg_match('/комбинированного нагрева/u', $section) => 'Бойлер комбинированного нагрева',
            (bool) preg_match('/бойлер|косвенн|емкостн|водонагреват|магниевый анод/u', $section) && ! preg_match('/принадлежн|крепление/u', mb_strtolower($name)) => 'Бойлер косвенного нагрева',
            (bool) preg_match('/расширительн|мембранн/u', $section) && ! preg_match('/комплектующ/u', $section) => 'Расширительный бак',
            (bool) preg_match('/сепараторы воздуха и шлама/u', $section) => 'Сепаратор воздуха и шлама',
            (bool) preg_match('/сепараторы шлама/u', $section) => 'Сепаратор шлама',
            (bool) preg_match('/сепараторы воздуха/u', $section) => 'Сепаратор воздуха',
            (bool) preg_match('/насосн\S* групп|насосный модуль/u', $section) && ! preg_match('/^(насосн|насосный)/iu', $name) => 'Насосная группа',
            (bool) preg_match('/гидравлическ\S* разделит|гидрострел/u', $section) && ! preg_match('/^гидравлическ/iu', $name) => 'Гидравлический разделитель',
            default => '',
        };
        // «1” без насоса» under «Насосная группа MTRE»: the section names the item.
        if ($type !== 'ТЭН' && preg_match('/^[\d"”«]/u', $name) && trim((string) ($raw['section'] ?? '')) !== ''
            && ! preg_match('/^принадлежност/iu', (string) $raw['section'])) {
            $name = trim((string) $raw['section']).' '.$name;
            $type = '';
        }
        // A name in words («Воздухоотводчик автоматический…») is the title itself.
        if ($type === '' && preg_match('/^\p{Cyrillic}/u', $name)) {
            return Str::limit($name.(mb_stripos($name, $brand) === false && $brand !== '' ? ', '.$brand : ''), 180, '');
        }
        $hasBrand = $brand !== '' && mb_stripos($name, $brand) !== false;

        return Str::limit(trim(($type !== '' ? $type.' ' : '').($hasBrand ? '' : $brand.' ').$name), 180, '');
    }

    public static function categorySlug(string $title): string
    {
        $title = mb_strtolower($title);
        foreach (self::CATEGORIES as $pattern => $slug) {
            if (preg_match($pattern, $title)) {
                return $slug;
            }
        }

        return '';
    }

    /** The one row of our 1C base with this article in its code or name. */
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

    private function createCard(SupplierProduct $offer, string $title, int $categoryId, int $brandId, string $brandName): Product
    {
        $raw = (array) $offer->raw;
        $sku = 'PRESTIZH-'.Str::upper(Str::limit(Str::slug((string) $offer->supplier_article), 60, ''));
        $specs = array_values(array_filter([
            ['key' => 'Артикул производителя', 'value' => (string) ($raw['article'] ?? '')],
            ['key' => 'Производитель', 'value' => $brandName],
            ['key' => 'Серия', 'value' => (string) ($raw['section'] ?? '')],
        ], fn (array $spec) => $spec['value'] !== ''));
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
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
            'meta_description' => Str::limit($title.' — цена, характеристики и доставка по Беларуси.', 175, ''),
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
