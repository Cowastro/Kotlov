<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\FirewayTeplodvorCatalog;
use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

/**
 * Cards for the Fireway rows of the Камин-профессионал price list no card of ours carries. The category comes
 * from the sheet and the title, the price is the РРЦ, the specs are those of the row. Photos, specs and the
 * description come from the Fireway page of teplodvor.by when there is one; otherwise the photo is the picture
 * next to the row in the price list and the description is written from the specs. A card without a photo
 * stays hidden (awaiting photos).
 *
 *   php artisan supplier:kaminproff-create                      # plan: per target category
 *   php artisan supplier:kaminproff-create --limit=20 --apply   # create and fill
 */
class CreateKaminproffCardsCommand extends Command
{
    protected $signature = 'supplier:kaminproff-create
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for Fireway offers of Камин-профессионал with teplodvor.by or price list photos';

    private const IMAGE_DIR = 'img/products/fireway-price';

    public function handle(FirewayTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncKaminproffCommand::SUPPLIER_CODE)->first();
        $path = KaminproffPriceList::storedPath();
        if (! $supplier || $path === '') {
            $this->line('metric.kaminproff_create.supplier=0');

            return self::FAILURE;
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $brandId = (int) DB::table('brands')->get(['id', 'name'])->first(fn ($brand) => SyncKaminproffCommand::isBrand((string) $brand->name))?->id;
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->where('stock_status', '!=', 'out_of_stock')->orderBy('id')->get();
        $pictures = $this->option('apply') ? KaminproffPriceList::pictures($path) : [];
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'on_site' => 0, 'no_category' => 0, 'created' => 0, 'with_photo' => 0, 'price_list_photo' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $name = (string) $offer->supplier_name;
            $slug = collect(self::targetCategories((string) ($raw['sheet'] ?? ''), $name))->first(fn (string $slug) => isset($categories[$slug]));
            if ($slug === null) {
                $stats['no_category']++;
                $this->line(sprintf('audit.kaminproff_create.no_category=%s', $offer->supplier_article));

                continue;
            }
            $stats['planned']++;
            $plan[$slug] = ($plan[$slug] ?? 0) + 1;
            $url = $site->find($name);
            $stats['on_site'] += (int) ($url !== null);
            $this->line(sprintf('audit.kaminproff_create.offer=%s:%s:%s', $offer->supplier_article, $slug, $url ? 'teplodvor' : 'price'));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $url ? $site->page($url) : null;
                $product = $this->createCard($offer, $name, (int) $categories[$slug], $brandId);
                $stats['created']++;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'https://kaminproff.by/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    // Without a page the specs of the row are the source of the text.
                    'description' => (string) ($page['desc'] ?? '') ?: trim($name.'. '.(string) ($raw['specs'] ?? '')),
                ], [
                    'verified_images' => $images, 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
                    'update_content' => true, 'min_specs_for_ai' => 2, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if ($product->imagePaths() === [] && ($picture = $pictures[(string) ($raw['sheet'] ?? '')][(int) ($raw['row'] ?? 0)] ?? null)) {
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
            $this->line(sprintf('audit.kaminproff_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.kaminproff_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our categories of a Fireway item, the first existing one wins: by the price list sheet and the title. */
    public static function targetCategories(string $sheet, string $name): array
    {
        $n = mb_strtolower($name);
        $has = fn (string ...$words): bool => collect($words)->contains(fn ($word) => str_contains($n, $word));

        return match (mb_strtolower($sheet)) {
            'камины, печи' => $has('топка') ? ['topki'] : ['pechi-kaminy'],
            'банные печи' => $has('сетка') ? ['aksessuary-dlya-bani'] : ['drovianye-peci-bannye'],
            'печное литье' => ['pechnoe-i-kaminnoe-lite'],
            'двери банные' => ['dveri-dlya-ban-i-saun', 'dveri-dlya-bani-i-sauny', 'dveri-dlya-bani'],
            'прочее' => match (true) {
                $has('master flash') => ['krovelnye-prohodki-master-flash'],
                $has('одеяло') => ['teploizolyatsiya-i-zashchitnye-listy-dymohoda', 'teploizoliaciia'],
                default => ['aksessuary-dlya-bani'],
            },
            default => [],
        };
    }

    /** «Дверь стеклянная FireWay 2х0,8 бронза» → «Дверь для бани стеклянная Fireway 2000×800 бронза». */
    public static function cardTitle(string $name): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/\bfire\s*way\b/iu', ' ', $name)));
        $title = strtr($title, ['ддля' => 'для', 'крёпежных' => 'крепёжных', 'Паровар' => 'ПароВар', ' c ' => ' с ', 'Печь банная' => 'Банная печь', 'Банная Печь' => 'Банная печь']);
        $title = (string) preg_replace('/^Дверь стеклянная/u', 'Дверь для бани стеклянная', $title);
        $title = (string) preg_replace_callback('/(?<![\d.,])(\d(?:[.,]\d+)?)\s*[xх]\s*(\d(?:[.,]\d+)?)(?![\d.,])/u',
            fn (array $m): string => round((float) str_replace(',', '.', $m[1]) * 1000).'×'.round((float) str_replace(',', '.', $m[2]) * 1000), $title);
        $title = (string) preg_replace('/(\d)\s*[xх*]\s*(\d)/u', '$1×$2', $title);
        $title = (string) preg_replace('/(\d)мм/u', '$1 мм', $title);
        $types = 'Каминная топка|Печь отопительно-варочная|Печь отопительная|Банная печь|Дверь для бани стеклянная|Дверь поддувальная|Дверца|Дверка прочистная|Колосник|Задвижка дымохода|Задвижка|Плита|Шкаф духовой|Одеяло огнеупорное|Сетка на трубу|Лампа для бани';
        if (preg_match('/^('.$types.')\s+(.*)$/u', $title, $m)) {
            return $m[1].' Fireway '.$m[2];
        }

        return $title.' Fireway';
    }

    /** «Мощность: 10 кВТ Габариты (Ш×Г×В): 660×360×650 mm. Вес: 101 кг» → specs rows. */
    public static function specsFromRow(string $text): array
    {
        $labels = 'Мощность|Габариты\s*\(?Ш×Г×В\)?|Вес|Диаметр дымохода|Цвет|Посадочный размер|Габаритный размер|Глубина|Высота|Ширина';
        preg_match_all('/('.$labels.')\s*[:\-]?\s*(.*?)(?=\s*(?:'.$labels.')\b|$)/u', $text, $m, PREG_SET_ORDER);
        $specs = [];
        foreach ($m as [, $key, $value]) {
            $key = (string) preg_replace('/^Габариты.*/u', 'Габариты (Ш×Г×В)', trim($key));
            $value = trim((string) preg_replace(['/\bmm\b/u', '/кВТ/u', '/\s+/u'], ['мм', 'кВт', ' '], $value), " .;,\t");
            if ($value !== '') {
                $specs[] = ['key' => $key, 'value' => $value];
            }
        }

        return $specs;
    }

    private function createCard(SupplierProduct $offer, string $name, int $categoryId, int $brandId): Product
    {
        $raw = (array) $offer->raw;
        $title = self::cardTitle($name);
        $sku = 'FIREWAY-'.Str::upper(Str::limit(Str::slug($name), 60, ''));
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId ?: null,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'short_description' => Str::limit(trim($title.'. '.((string) ($raw['specs'] ?? '') ?: 'Производство Fireway, Россия.')), 250, ''),
            'images' => [],
            'specs' => array_merge(self::specsFromRow((string) ($raw['specs'] ?? '')), [
                ['key' => 'Производитель', 'value' => 'Fireway'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ]),
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
