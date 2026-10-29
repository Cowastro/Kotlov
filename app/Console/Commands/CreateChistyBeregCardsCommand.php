<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\BeregSiteCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the Чистый берег offers no card of ours carries (stage 1: heating). The category comes
 * from the supplier category and the title; photos, specs and description from the product page on
 * bereg.by (found by the 8-digit code); the price is the recommended retail. A card without a photo
 * stays hidden (awaiting photos) until one is found. Marked-down rows («Уценка …») are left out.
 *
 *   php artisan supplier:chisty-bereg-create                         # plan: per target category
 *   php artisan supplier:chisty-bereg-create --limit=50 --apply      # create and fill the next 50
 */
class CreateChistyBeregCardsCommand extends Command
{
    protected $signature = 'supplier:chisty-bereg-create
        {--group=* : Supplier categories (slug) instead of all heating ones}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for Чистый берег offers (heating) with bereg.by photos and specs';

    /** Supplier categories of stage 1 (heating), by slug. */
    private const HEATING = ['nasosy', 'kotly-i-dymoxody-avtomatika', 'otopitelnoe-oborudovanie', 'vodonagrevateli-i-bufernye-emkosti',
        'baki-rassiritelnye-membrannye', 'termostatika-i-balansirovka', 'krany', 'klapany', 'ventili-klapany-zapornye', 'zadvizki', 'zatvory',
        'filtry', 'pribory-uceta-i-kontrolia', 'reguliatory-kondensatootvodciki', 'itp-ind-teplovoi-punkt', 'privody'];

    private const COUNTRIES = 'Россия|РФ|Китай|Италия|Германия|Беларусь|РБ|Польша|Турция|Словения|Чехия|Сербия|Франция|Испания|Дания|Швеция|Финляндия|Корея|Япония|Украина|Венгрия|Австрия|Швейцария|Нидерланды|Бельгия|Португалия|Литва|Латвия|Эстония|Болгария|Румыния|Словакия|Великобритания|США|Индия|Тайвань|Вьетнам|Малайзия|Израиль|Казахстан';

    public function handle(BeregSiteCatalog $bereg, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncChistyBeregCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.chisty_bereg_create.supplier=0');

            return self::FAILURE;
        }
        $groups = (array) $this->option('group') ?: self::HEATING;
        $categories = DB::table('categories')->pluck('id', 'slug');
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->orderBy('supplier_article')->get()
            ->filter(fn (SupplierProduct $offer) => in_array(Str::slug((string) data_get($offer->raw, 'category')), $groups, true));

        $stats = ['offers' => $offers->count(), 'planned' => 0, 'no_category' => 0, 'markdown' => 0, 'created' => 0, 'with_photo' => 0, 'page_not_found' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $name = (string) $offer->supplier_name;
            if (Str::startsWith(mb_strtolower($name), 'уценка')) {
                $stats['markdown']++;

                continue;
            }
            $slug = self::targetCategory((string) data_get($offer->raw, 'category'), $name);
            if ($slug === null || ! isset($categories[$slug])) {
                $stats['no_category']++;
                $this->line(sprintf('audit.chisty_bereg_create.no_category=%s:%s', $offer->supplier_article, Str::limit(Str::slug($name), 70, '')));

                continue;
            }
            $stats['planned']++;
            $plan[$slug] = ($plan[$slug] ?? 0) + 1;
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $url = $bereg->find((string) $offer->supplier_article);
                $page = $url ? $bereg->page($url, (string) $offer->supplier_article) : null;
                $stats['page_not_found'] += (int) ($page === null);
                $product = $this->createCard($offer, (int) $categories[$slug], $page);
                $stats['created']++;
                if ($page !== null) {
                    $enricher->enrichFromParsed($product, $url, [
                        'images' => [],
                        'specs' => $page['specs'],
                        'description' => $page['description'],
                    ], [
                        'verified_images' => $page['images'], 'update_images' => $page['images'] !== [], 'update_specs' => true,
                        'update_content' => $page['description'] !== '' || count($page['specs']) >= 3, 'update_service' => false,
                        'update_documents' => false, 'update_video' => false,
                    ]);
                }
                $product->refresh();
                if ($product->imagePaths() !== []) {
                    $product->forceFill(['is_active' => true, 'created_via' => null])->save();
                    $stats['with_photo']++;
                }
                app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        foreach ($plan as $slug => $count) {
            $this->line(sprintf('audit.chisty_bereg_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.chisty_bereg_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our category of an offer: the supplier category narrowed by the title. */
    public static function targetCategory(string $supplierCategory, string $name): ?string
    {
        $n = mb_strtolower($name);
        $has = fn (string ...$words): bool => collect($words)->contains(fn ($word) => str_contains($n, $word));

        return match (Str::slug($supplierCategory)) {
            'nasosy' => match (true) {
                ! Str::startsWith($n, ['насос', 'станция', 'насосная']) => 'komplektuyushchie-dlya-nasosov',
                $has('скважин', 'погружн', 'вибрац') => 'skvajinnye-nasosy',
                $has('дренаж', 'фекал') => 'drenajnyie',
                $has('станци') => 'nasosnyie-stantsii',
                // The model names the type where the title does not: UPS, Magna, Star, APM — circulators; MHI, MVL — multistage.
                $has('циркуляц') || (bool) preg_match('/(?<![a-z])(ups|magna|alpha|star|stratos|yonos|calio|apm|ghn|gpd|noc)(?![a-z])/u', $n) => 'tsirkulyatsionnyie',
                $has('многоступ') || (bool) preg_match('/(?<![a-z])(mhi|mhil|mvl|mvi)(?![a-z])/u', $n) => 'mnogostupenchatye-nasosy',
                $has('поверхност', 'самовсас', 'вихрев', 'центробеж', 'medana', 'cmf', 'jet') => 'poverhnostnyie',
                default => null,
            },
            'kotly-i-dymoxody-avtomatika' => match (true) {
                Str::startsWith($n, ['котел', 'котёл']) => match (true) {
                    $has('газ') => 'gazovye',
                    $has('электр') => 'elektricheskie',
                    $has('пеллет') => 'kotly-na-pelletah',
                    $has('твердотоп', 'дров', 'угол') => 'tverdotoplivnye',
                    default => null,
                },
                $has('датчик') => 'datchiki',
                $has('групп') => 'gruppy-bystrogo-montazha-kotelnyx',
                $has('разделител', 'гидрострел', 'коллектор') => 'gidravlicheskie-razdeliteli-i-kollektory',
                $has('тэн') => 'elektricheskie-teny-dlya-otopleniya',
                $has('трехход', 'трёхход', 'смесит') => 'smesitelnye-klapany-i-uzly',
                $has('удлинит', 'дымоход', 'коаксиал', 'наконечник') => 'koaxial-dymoxod',
                $has('термостат', 'терморегулят', 'контроллер', 'регулятор', 'блок управ', 'панель', 'блок индик', 'реле') => 'regulyatory',
                Str::startsWith($n, 'клапан') => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
                default => 'komplektuyushhie-dlya-otopleniya',
            },
            'otopitelnoe-oborudovanie' => match (true) {
                Str::startsWith($n, 'радиатор') => match (true) {
                    $has('биметалл') => 'bimetallicheskie-radiatory',
                    $has('алюмин') => 'alyuminievye-radiatory',
                    default => 'stalnye-radiatory',
                },
                Str::startsWith($n, 'конвектор') => 'vodyanye-konvektory',
                default => 'radiatornaya-armatura',
            },
            'vodonagrevateli-i-bufernye-emkosti' => ! Str::startsWith($n, ['водонагреватель', 'электрический', 'бойлер', 'емкость', 'ёмкость', 'буфер', 'теплоаккум']) ? null : match (true) {
                $has('косвенн') => 'kosvennye',
                $has('буфер', 'теплоаккум', 'емкость', 'ёмкость') => 'bufernye-emkosti',
                $has('комбинир') => 'kombinirovannye',
                $has('газов') => 'gas',
                default => 'electric',
            },
            'baki-rassiritelnye-membrannye' => 'membrannye-baki',
            'termostatika-i-balansirovka' => match (true) {
                $has('трехход', 'трёхход', 'смесит') => 'smesitelnye-klapany-i-uzly',
                $has('балансир', 'перепад', 'регулятор') => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
                default => 'radiatornaya-armatura',
            },
            'krany' => $has('маевск', 'радиатор') ? 'radiatornaya-armatura' : 'sharovye-krany',
            'klapany' => match (true) {
                $has('обратн') => 'obratnye-klapany',
                $has('трехход', 'трёхход', 'смесит') => 'smesitelnye-klapany-i-uzly',
                default => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
            },
            'ventili-klapany-zapornye' => 'ventili-zapornye',
            'zadvizki' => 'zadvizhki',
            'zatvory' => 'zatvory-diskovye',
            'filtry' => 'filtry',
            'pribory-uceta-i-kontrolia' => $has('счетчик', 'счётчик', 'теплосчет', 'теплосчёт') ? 'schetchiki-vody-i-tepla' : 'manometry-i-termometry',
            'reguliatory-kondensatootvodciki' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
            'itp-ind-teplovoi-punkt' => $has('датчик') ? 'datchiki' : 'regulyatory',
            'privody' => 'smesitelnye-klapany-i-uzly',
            default => null,
        };
    }

    private function createCard(SupplierProduct $offer, int $categoryId, ?array $page): Product
    {
        $raw = (array) $offer->raw;
        $brandName = trim((string) ($raw['brand'] ?? ''));
        $brandId = in_array(mb_strtolower($brandName), ['', 'без бренда'], true) ? null : $this->brandId($brandName);
        // The site title has no «… Китай»; the price list name ends with the country of make.
        $country = preg_match('/\s('.self::COUNTRIES.')$/u', (string) $offer->supplier_name, $m) ? $m[1] : null;
        $name = trim((string) ($page['title'] ?? '')) ?: trim((string) preg_replace('/\s('.self::COUNTRIES.')$/u', '', (string) $offer->supplier_name));
        $retail = (float) ($raw['retail'] ?? 0);
        $sku = 'BEREG-'.$offer->supplier_article;

        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'h1' => $name,
            'slug' => $this->uniqueSlug($name),
            'sku' => $sku,
            'price' => $retail,
            'currency' => 'BYN',
            'short_description' => Str::limit($name.'. '.collect([$brandName && $brandId ? 'Бренд '.$brandName : null, $country ? 'производство: '.$country : null])->filter()->implode(', '), 250, ''),
            'images' => [],
            'specs' => array_values(array_filter([
                ['key' => 'Код производителя', 'value' => (string) ($raw['maker_code'] ?? '')],
                ['key' => 'Страна производства', 'value' => (string) $country],
            ], fn ($spec) => $spec['value'] !== '')),
            'unit' => 'шт',
            'is_active' => false,
            'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS,
            'is_archived' => false,
            'is_new' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'meta_title' => Str::limit($name, 60, '').' купить в %city%',
            'meta_description' => Str::limit($name.' — цена, наличие и доставка по Беларуси.', 175, ''),
        ]);
        $offer->forceFill(['product_id' => $product->id, 'product_sku' => $sku, 'match_status' => 'matched', 'match_confidence' => 'created_from_price'])->save();

        return $product;
    }

    /** @var array<string,int>|null brand key => id */
    private ?array $brands = null;

    private function brandId(string $name): int
    {
        $key = Str::slug($name, '');
        $this->brands ??= DB::table('brands')->get(['id', 'name'])->mapWithKeys(fn ($brand) => [Str::slug((string) $brand->name, '') => (int) $brand->id])->all();
        if (isset($this->brands[$key])) {
            return $this->brands[$key];
        }
        $slug = Str::slug($name) ?: 'brand';
        $base = $slug;
        for ($i = 2; DB::table('brands')->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $this->brands[$key] = (int) DB::table('brands')->insertGetId(['name' => $name, 'slug' => $slug, 'h1' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
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
