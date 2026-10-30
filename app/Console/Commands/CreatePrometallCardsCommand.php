<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\BanipechiCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the ProMetall offers no card of ours carries. The category comes from the price list section and
 * the title; photos, specs and description from the dealer's page on banipechi.by, found by the ProMetall
 * article; the price is the РРЦ. A card without a photo stays hidden (awaiting photos) until one is found.
 *
 *   php artisan supplier:prometall-create                      # plan: per target category
 *   php artisan supplier:prometall-create --limit=50 --apply   # create and fill
 */
class CreatePrometallCardsCommand extends Command
{
    protected $signature = 'supplier:prometall-create
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for ProMetall offers with banipechi.by photos and specs';

    public function handle(BanipechiCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncPrometallCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.prometall_create.supplier=0');

            return self::FAILURE;
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $brandId = (int) DB::table('brands')->get(['id', 'name'])->first(fn ($brand) => SyncPrometallCommand::isBrand((string) $brand->name))?->id;
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')->orderBy('id')->get();
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'on_site' => 0, 'no_category' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $name = (string) preg_replace('/^ProMetall\s+/u', '', (string) $offer->supplier_name);
            $slug = self::targetCategory((string) ($raw['section'] ?? ''), $name);
            if ($slug === null || ! isset($categories[$slug])) {
                $stats['no_category']++;
                $this->line(sprintf('audit.prometall_create.no_category=%s:%s', Str::slug((string) $offer->supplier_article), Str::limit(Str::slug($name), 60, '')));

                continue;
            }
            $stats['planned']++;
            $plan[$slug] = ($plan[$slug] ?? 0) + 1;
            $url = $site->find((string) $offer->supplier_article);
            $stats['on_site'] += (int) ($url !== null);
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $url ? $site->page($url) : null;
                $product = $this->createCard($offer, $name, (int) $categories[$slug], $brandId, $page);
                $stats['created']++;
                if ($page !== null) {
                    $enricher->enrichFromParsed($product, $url, [
                        'images' => [],
                        'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($page['specs'], (array) $product->specs),
                        'description' => $page['description'],
                    ], [
                        'verified_images' => $page['images'], 'update_images' => $page['images'] !== [], 'update_specs' => true, 'replace_specs' => true,
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
            $this->line(sprintf('audit.prometall_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.prometall_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our category of a ProMetall item: by the price list section, narrowed by the first words of the title. */
    public static function targetCategory(string $section, string $name): ?string
    {
        $n = mb_strtolower($name);
        $has = fn (string ...$words): bool => collect($words)->contains(fn ($word) => str_contains($n, $word));
        $s = mb_strtolower($section);

        return match (true) {
            str_starts_with($s, 'печи "') => 'drovianye-peci-bannye',
            $s === 'печное литье' => 'pechnoe-i-kaminnoe-lite',
            $s === 'аксессуары для бани' => $has('камни', 'пирамидки') ? 'kamni-dlya-bani' : 'pechi-dlya-bani',
            $s === 'дымоходные элементы на банные печи' => $has('конвектор') ? 'teplosyomniki' : 'truby-mono',
            $s === 'комплектующие к печам' => match (true) {
                $has('стекл') => 'stekla-dlya-pechey-i-kaminov',
                $has('краска') => 'termostoykie-kraski',
                default => 'aksessuary-dlya-bani',
            },
            $s === 'печи отопительные и аксессуары' => match (true) {
                $has('печь-камин', 'печь- камин') => 'pechi-kaminy',
                $has('конвектор') => 'teplosyomniki',
                default => 'peci-drovianye-otopitelnye',
            },
            in_array($s, ['натрубные элементы', 'наборы ламелей', 'порталы из камня'], true) => 'aksessuary-dlya-bani',
            default => null,
        };
    }

    private function createCard(SupplierProduct $offer, string $name, int $categoryId, int $brandId, ?array $page): Product
    {
        $raw = (array) $offer->raw;
        $title = trim((string) ($page['title'] ?? '')) ?: $name;
        $sku = 'PROMETALL-'.Str::upper((string) $offer->supplier_article);
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId ?: null,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'short_description' => Str::limit(trim($title.'. '.((string) ($raw['description'] ?? '') ?: 'Производство ProMetall, Россия.')), 250, ''),
            'images' => [],
            'specs' => array_values(array_filter([
                ['key' => 'Артикул производителя', 'value' => (string) $offer->supplier_article],
                ['key' => 'Штрихкод', 'value' => (string) ($raw['ean'] ?? '')],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ], fn ($spec) => $spec['value'] !== '')),
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
