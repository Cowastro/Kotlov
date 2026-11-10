<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\SmolkomPriceList;
use App\Services\Suppliers\SmolkomTeplodvorCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the Смолком rows no card of ours carries: electric fireplaces (Dimplex, Royal Flame, Real Flame) and
 * Smolcom portals, one card per row (a portal colour is its own card, as in the price list). The category is the
 * one the linked cards of the same kind already sit in; the price is the РРЦ; the sizes and colour of the price
 * list are the first specs. Photos, specs and the description come from the teplodvor.by page of the model when
 * there is one; otherwise the card waits hidden for a photo (supplier:chisty-bereg-photos --supplier=smolkom).
 * A card is never made where one of the same brand, model and colour exists.
 *
 *   php artisan supplier:smolkom-create                        # plan
 *   php artisan supplier:smolkom-create --limit=30 --apply     # create and fill
 */
class CreateSmolkomCardsCommand extends Command
{
    protected $signature = 'supplier:smolkom-create
        {--group=all : all, electric (electric fireplaces) or portals}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for Смолком offers (electric fireplaces and Smolcom portals) with teplodvor.by photos';

    /** Our category of each kind when no linked card shows one, the first existing wins. */
    private const FALLBACK_CATEGORIES = [
        'electric' => ['elektrokamini', 'elektrokaminy', 'elektrokamin'],
        'portals' => ['portaly-dlya-elektrokaminov', 'portaly', 'kaminokomplekty', 'elektrokamini', 'elektrokaminy'],
    ];

    public function handle(SmolkomTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncSmolkomCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.smolkom_create.supplier=0');

            return self::FAILURE;
        }
        $group = (string) $this->option('group');
        $brandIds = SyncSmolkomCommand::brandIds();
        $categoryOf = $this->categories($supplier);
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->where('stock_status', '!=', 'out_of_stock')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => (float) ($offer->raw['retail'] ?? 0) > 0 && empty($offer->raw['no_auto_link'])
                && ($group === 'all' || self::kind((array) $offer->raw) === $group))
            ->values();
        $existing = [];
        $similar = [];
        // A card already linked to a row of another model is that model, not a spelling of this one.
        $linkedModel = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->get(['product_id', 'raw'])
            ->mapWithKeys(fn (SupplierProduct $o) => [(int) $o->product_id => SmolkomPriceList::modelKey((string) ($o->raw['name'] ?? ''))]);
        foreach (DB::table('products')->whereIn('brand_id', array_merge(...array_values($brandIds ?: [[0]])))->where('is_archived', false)->get(['id', 'name', 'brand_id']) as $card) {
            $existing[$card->brand_id.'|'.SmolkomPriceList::modelKey((string) $card->name).'|'.implode(',', SmolkomPriceList::colourWords((string) $card->name))] = true;
            $similar[(int) $card->brand_id][] = ['words' => SmolkomPriceList::modelWords((string) $card->name), 'colours' => SmolkomPriceList::colourWords((string) $card->name),
                'linked_key' => $linkedModel[(int) $card->id] ?? null];
        }

        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'no_brand' => 0, 'no_category' => 0, 'on_site' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $code = Str::slug((string) $offer->supplier_article);
            $kind = self::kind($raw);
            $brandId = (int) ($brandIds[$raw['brand'] ?? ''][0] ?? 0);
            if (! $brandId) {
                $stats['no_brand']++;
                $this->line('audit.smolkom_create.no_brand='.$code);

                continue;
            }
            $colours = implode(',', SmolkomPriceList::colourWords((string) ($raw['colour'] ?? '')));
            if (isset($existing[$brandId.'|'.SmolkomPriceList::modelKey((string) ($raw['name'] ?? '')).'|'.$colours])
                || self::hasSimilar($similar[$brandId] ?? [], SmolkomPriceList::modelWords((string) ($raw['name'] ?? '')), SmolkomPriceList::colourWords((string) ($raw['colour'] ?? '')))) {
                $stats['card_exists']++;
                $this->line('audit.smolkom_create.card_exists='.$code);

                continue;
            }
            $category = $categoryOf[$kind] ?? null;
            if (! $category) {
                $stats['no_category']++;
                $this->line('audit.smolkom_create.no_category='.$code);

                continue;
            }
            $stats['planned']++;
            $plan[$category['slug']] = ($plan[$category['slug']] ?? 0) + 1;
            $url = $site->find(($raw['brand'] ?? '').' '.($raw['name'] ?? '').' '.($raw['colour'] ?? ''));
            $stats['on_site'] += (int) ($url !== null);
            $this->line(sprintf('audit.smolkom_create.offer=%s:%s:%s', $code, $category['slug'], $url ? 'teplodvor' : 'price'));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $url ? $site->page($url) : null;
                $product = $this->createCard($offer, $kind, (int) $category['id'], $brandId);
                $existing[$brandId.'|'.SmolkomPriceList::modelKey((string) ($raw['name'] ?? '')).'|'.$colours] = true;
                $stats['created']++;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'https://kotlov.by/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    'description' => (string) ($page['desc'] ?? '') ?: trim($product->name.'. '.collect((array) $product->specs)->map(fn ($s) => $s['key'].': '.$s['value'])->implode('; ')),
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
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        foreach ($plan as $slug => $count) {
            $this->line(sprintf('audit.smolkom_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.smolkom_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /**
     * A card of the brand whose model words hold the row's or are held by them («Luton FS33/FS33W/L33» for «LUTON
     * FS33»), in the same colour or without one: probably the same item written otherwise, so no new card.
     */
    public static function hasSimilar(array $cards, array $words, array $colours): bool
    {
        foreach ($cards as $card) {
            if (($card['linked_key'] ?? null) !== null && $card['linked_key'] !== implode(' ', $words)) {
                continue;
            }
            // The same model without a colour is the white one: other colours become cards of their own, offered as
            // «Выберите цвет» on the card page.
            if ($card['colours'] === [] && $card['words'] === $words && $colours !== []) {
                continue;
            }
            $colourOk = $card['colours'] === [] || $card['colours'] === $colours;
            $common = array_intersect($card['words'], $words);
            if ($colourOk && count($common) >= 2 && (array_diff($words, $card['words']) === [] || array_diff($card['words'], $words) === [])) {
                return true;
            }
        }

        return false;
    }

    public static function kind(array $raw): string
    {
        return str_contains(mb_strtolower((string) ($raw['group'] ?? '')), 'портал') ? 'portals' : 'electric';
    }

    /** «ALBION V42», «Бьянко белый» → «Портал Smolcom Albion V42 бьянко белый»; «Vision 42 LED» → «Электрокамин Royal Flame Vision 42 LED». */
    public static function cardTitle(array $raw): string
    {
        $name = (string) preg_replace_callback('/\b(\p{Lu}{4,})\b/u', fn (array $m) => in_array($m[1], ['LCD', 'LED', 'RGB', 'PS'], true) ? $m[1] : Str::title(mb_strtolower($m[1])), trim((string) ($raw['name'] ?? '')));
        $name = trim((string) preg_replace('/[\s-]+$/u', '', $name));
        $brand = (string) ($raw['brand'] ?? '');
        $colour = mb_strtolower(trim((string) ($raw['colour'] ?? '')));
        if (self::kind($raw) === 'portals') {
            return trim('Портал '.$brand.' '.$name.' '.$colour);
        }
        $accessory = preg_match('/муляж|комплект|рамка|аксессуар/iu', $name) === 1;

        return trim(($accessory ? '' : 'Электрокамин ').$brand.' '.$name.($colour !== '' ? ' '.$colour : ''));
    }

    /** @return array<string, array{id:int,slug:string}> kind => category */
    private function categories(Supplier $supplier): array
    {
        $result = [];
        $linked = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->get(['product_id', 'raw']);
        $categoryIds = DB::table('products')->whereIn('id', $linked->pluck('product_id')->all() ?: [0])->pluck('category_id', 'id');
        foreach (['electric', 'portals'] as $kind) {
            $counts = $linked->filter(fn (SupplierProduct $o) => self::kind((array) $o->raw) === $kind)
                ->map(fn (SupplierProduct $o) => $categoryIds[$o->product_id] ?? null)->filter()->countBy()->sortDesc();
            $id = (int) ($counts->keys()->first() ?? 0);
            $category = $id ? DB::table('categories')->where('id', $id)->first(['id', 'slug']) : null;
            $category ??= DB::table('categories')->whereIn('slug', self::FALLBACK_CATEGORIES[$kind])->get(['id', 'slug'])
                ->sortBy(fn ($c) => array_search($c->slug, self::FALLBACK_CATEGORIES[$kind], true))->first();
            if ($category) {
                $result[$kind] = ['id' => (int) $category->id, 'slug' => (string) $category->slug];
            }
        }

        return $result;
    }

    private function createCard(SupplierProduct $offer, string $kind, int $categoryId, int $brandId): Product
    {
        $raw = (array) $offer->raw;
        $title = self::cardTitle($raw);
        $sku = 'SMOLKOM-'.Str::upper(Str::limit(Str::slug((string) $offer->supplier_article), 60, ''));
        $specs = collect((array) ($raw['sizes'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
        if (filled($raw['colour'] ?? null)) {
            $specs[] = ['key' => 'Цвет', 'value' => mb_strtolower(trim((string) $raw['colour']))];
        }
        if (filled($raw['section'] ?? null)) {
            $specs[] = ['key' => $kind === 'portals' ? 'Под очаг' : 'Типоразмер', 'value' => (string) $raw['section']];
        }
        $specs[] = ['key' => 'Производитель', 'value' => (string) ($raw['brand'] ?? '')];
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
