<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\AkvateploservisPriceList;
use App\Services\Suppliers\GalmetTeplodvorCatalog;
use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

/**
 * Cards for the priced Galmet and Apamet rows of the Акватеплосервис price list no card of ours carries. The
 * category comes from the section and the title, the price is the РРЦ, the specs are those of the row. Photos,
 * specs and the description come from the teplodvor.by page (found by the article or the model) when there is
 * one; otherwise the photo is the picture next to the row in the price list and the description is written
 * from the specs. A card without a photo stays hidden (awaiting photos).
 *
 *   php artisan supplier:akvateploservis-create                      # plan: per target category
 *   php artisan supplier:akvateploservis-create --limit=20 --apply   # create and fill
 */
class CreateAkvateploservisCardsCommand extends Command
{
    protected $signature = 'supplier:akvateploservis-create
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for Galmet offers of Акватеплосервис with teplodvor.by or price list photos';

    private const IMAGE_DIR = 'img/products/galmet-price';

    public function handle(GalmetTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncAkvateploservisCommand::SUPPLIER_CODE)->first();
        $path = AkvateploservisPriceList::storedPath();
        if (! $supplier || $path === '') {
            $this->line('metric.akvateploservis_create.supplier=0');

            return self::FAILURE;
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $brands = DB::table('brands')->get(['id', 'name'])->filter(fn ($brand) => SyncAkvateploservisCommand::isBrand((string) $brand->name))
            ->mapWithKeys(fn ($brand) => [Str::slug((string) $brand->name, '') => (int) $brand->id]);
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->where('stock_status', '!=', 'out_of_stock')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => (float) ($offer->raw['retail'] ?? 0) > 0)->values();
        $pictures = $this->option('apply') ? KaminproffPriceList::pictures($path) : [];
        $sheet = $pictures !== [] ? array_key_first($pictures) : '';
        // A card of the same article or model already exists (two of them left the row unlinked): never a third one.
        $existing = [];
        foreach (DB::table('products')->whereIn('brand_id', $brands->values())->where('is_archived', false)->get(['name', 'sku']) as $card) {
            $existing[AkvateploservisPriceList::modelKey((string) $card->name)] = true;
            preg_match_all('/\b\d{2}-\d{6}N?\b/u', mb_strtoupper($card->name.' '.$card->sku), $m);
            foreach ($m[0] as $article) {
                $existing['art:'.$article] = true;
            }
        }
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'on_site' => 0, 'no_category' => 0, 'created' => 0, 'with_photo' => 0, 'price_list_photo' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $name = trim((string) preg_replace('/^(Galmet|Apamet)\s+/u', '', (string) $offer->supplier_name));
            $slug = collect(self::targetCategories((string) ($raw['section'] ?? ''), $name))->first(fn (string $slug) => isset($categories[$slug]));
            $code = Str::slug((string) $offer->supplier_article);
            if (isset($existing[AkvateploservisPriceList::modelKey($name)]) || isset($existing['art:'.mb_strtoupper((string) ($raw['article'] ?? ''))])) {
                $stats['card_exists']++;
                $this->line(sprintf('audit.akvateploservis_create.card_exists=%s', $code));

                continue;
            }
            if ($slug === null) {
                $stats['no_category']++;
                $this->line(sprintf('audit.akvateploservis_create.no_category=%s', $code));

                continue;
            }
            $stats['planned']++;
            $plan[$slug] = ($plan[$slug] ?? 0) + 1;
            $url = $site->findOffer($name, (string) ($raw['article'] ?? ''));
            $stats['on_site'] += (int) ($url !== null);
            $this->line(sprintf('audit.akvateploservis_create.offer=%s:%s:%s', $code, $slug, $url ? 'teplodvor' : 'price'));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $url ? $site->page($url) : null;
                $brand = (string) ($raw['brand'] ?? 'Galmet');
                $product = $this->createCard($offer, $name, $brand, $slug, (int) $categories[$slug], (int) ($brands[Str::slug($brand, '')] ?? $brands['galmet'] ?? 0));
                $stats['created']++;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'http://galmet.by/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    // Without a page the specs of the row are the source of the text.
                    'description' => (string) ($page['desc'] ?? '') ?: trim($product->name.'. '.(string) ($raw['specs'] ?? '')),
                ], [
                    'verified_images' => $images, 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
                    'update_content' => true, 'min_specs_for_ai' => 2, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if ($product->imagePaths() === [] && ($picture = $pictures[$sheet][(int) ($raw['row'] ?? 0)] ?? null)) {
                    if ($image = $this->savePicture($picture, $product->slug)) {
                        $product->forceFill(['images' => [$image]])->save();
                        $stats['price_list_photo']++;
                    }
                }
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
            $this->line(sprintf('audit.akvateploservis_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.akvateploservis_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our categories of an item, the first existing one wins: by the price list section and the title. */
    public static function targetCategories(string $section, string $name): array
    {
        $s = mb_strtolower($section.' '.$name);
        $has = fn (string ...$words): bool => collect($words)->contains(fn ($word) => str_contains($s, $word));

        return match (true) {
            $has('luksus', 'блок управления') => ['regulyatory'],
            $has('гелио', 'коллектор', 'ksg') => ['solnechnye-kollektory'],
            $has('airmax') => ['teplovye-nasosy-vozduh-voda', 'teplovye-nasosy'],
            $has('maxima') => ['teplovye-nasosy-rassol-voda', 'teplovye-nasosy', 'teplovyie-nasosyi', 'teplovye-nasosy-vozduh-voda'],
            $has('тепловых насосов', 'тепловой насос') && $has('теплообменник') => ['komplektuyushchie-dlya-vodonagrevateley'],
            $has('блок-тэн', 'блок тэн', 'комплект электрический') || preg_match('/^\d+(,\d+)?\s*квт/u', mb_strtolower($name)) === 1 => ['komplektuyushchie-dlya-vodonagrevateley', 'elektricheskie-teny-dlya-otopleniya'],
            $has('электрические', 'max sg') => ['electric'],
            $has('крепление', 'прокладка', 'фланец', 'гильза', 'тэн ') => ['komplektuyushchie-dlya-vodonagrevateley'],
            $has('neptun') => ['kombinirovannye'],
            $has('multi-inox', 'kumulo', 'буфер', 'bufor') => ['bufernye-emkosti'],
            default => ['kosvennye'],
        };
    }

    /** «TOWER SGW(S) 300 Skay (w/s) FL, цвет - белый» → «Бойлер косвенного нагрева Galmet TOWER SGW(S) 300 Skay (w/s) FL, белый (26-308070N)». */
    public static function cardTitle(string $name, string $brand, string $category, string $article): string
    {
        $colour = preg_match('/цвет\s*-\s*([а-яё]+)/u', $name, $c) ? $c[1] : '';
        $side = preg_match('/справа/u', $name) ? 'правое подключение' : (preg_match('/слева/u', $name) ? 'левое подключение' : '');
        $model = trim((string) preg_replace('/\s*\([^)]*подключени[^)]*\)/u', '', $name));
        $model = preg_match('/^(.*?\d.*?)\s+-\s(?!\d)/u', $model, $m) ? $m[1] : $model;
        $model = preg_match('/^(.*?\d.*?),\s/u', $model, $m) ? $m[1] : $model;
        $type = match ($category) {
            'kosvennye' => 'Бойлер косвенного нагрева',
            'kombinirovannye' => 'Водонагреватель комбинированный',
            'electric' => 'Водонагреватель электрический',
            'bufernye-emkosti' => 'Буферная емкость',
            'solnechnye-kollektory' => 'Солнечный коллектор',
            'komplektuyushchie-dlya-vodonagrevateley', 'elektricheskie-teny-dlya-otopleniya' => preg_match('/^\d/u', $model) ? 'Блок ТЭН' : '',
            default => '',
        };
        // «Apamet Stil 150» already names its brand.
        $brand = str_starts_with(mb_strtolower($model), mb_strtolower($brand)) ? '' : $brand;
        $title = trim((string) preg_replace('/\s+/u', ' ', $type.' '.$brand.' '.$model)).implode('', array_map(fn ($extra) => ', '.$extra, array_filter([$colour, $side])));

        $title = Str::limit($title, 200, '');

        return $article !== '' ? $title.' ('.$article.')' : $title;
    }

    /** «Объем 153л, теплообменник 22.6/29,8 кВт (0,9 м2), полиуретан 50 мм, … высота 1100 мм, наружный Ø 550 мм. Класс…» → specs. */
    public static function specsFromRow(string $text): array
    {
        $labels = [
            '/^объ[её]м\s*(.+)$/iu' => 'Объём',
            '/^(?:два\s+)?теплообмен[а-я]*\s*(.+)$/iu' => 'Теплообменник',
            '/^(полиуретан.*|neodul.*)$/iu' => 'Теплоизоляция',
            '/^возможн[а-я]*\s+монтажа?\s+(.+)$/iu' => 'Возможность монтажа',
            '/^высота\s*(.+)$/iu' => 'Высота',
            '/^наружный\s*(?:ø|диаметр)\s*(.+)$/iu' => 'Наружный диаметр',
            '/^класс энергоэффективности\s*-?\s*(.+)$/iu' => 'Класс энергоэффективности',
        ];
        $specs = [];
        foreach (preg_split('/,\s+|\.\s+/u', trim($text)) ?: [] as $part) {
            $part = trim($part, " .\t");
            foreach ($labels as $pattern => $key) {
                if (preg_match($pattern, $part, $m) && ! isset($specs[$key])) {
                    $specs[$key] = ['key' => $key, 'value' => trim($m[1] ?? $part, ' .')];
                    break;
                }
            }
        }

        return array_values($specs);
    }

    private function createCard(SupplierProduct $offer, string $name, string $brand, string $category, int $categoryId, int $brandId): Product
    {
        $raw = (array) $offer->raw;
        $article = (string) ($raw['article'] ?? '');
        $title = self::cardTitle($name, $brand, $category, $article);
        $sku = $article !== '' ? 'GALMET-'.$article : 'APAMET-'.Str::upper(Str::limit(Str::slug($name), 50, ''));
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId ?: null,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'short_description' => Str::limit(trim($title.'. '.((string) ($raw['specs'] ?? '') ?: 'Производство '.$brand.'.')), 250, ''),
            'images' => [],
            'specs' => array_merge(self::specsFromRow((string) ($raw['specs'] ?? '')), array_values(array_filter([
                $article !== '' ? ['key' => 'Артикул производителя', 'value' => $article] : null,
                ['key' => 'Производитель', 'value' => $brand],
                ['key' => 'Страна производства', 'value' => $brand === 'Apamet' ? 'Турция' : 'Польша'],
            ]))),
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

    /** The picture of the row saved as the card photo; null for a tiny or unreadable one (a logo, a stamp). */
    private function savePicture(Drawing|MemoryDrawing $picture, string $slug): ?string
    {
        if ($picture instanceof MemoryDrawing) {
            ob_start();
            imagejpeg($picture->getImageResource(), null, 90);
            [$body, $extension] = [(string) ob_get_clean(), 'jpg'];
        } else {
            [$body, $extension] = [(string) @file_get_contents($picture->getPath()), strtolower($picture->getExtension() ?: 'jpg')];
        }
        if (strlen($body) < 3000 || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return null;
        }
        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $slug.'.'.$extension;
        file_put_contents($dir.DIRECTORY_SEPARATOR.$file, $body);

        return self::IMAGE_DIR.'/'.$file;
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
