<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductCreator;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\TeplovSiteCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Stainless chimney parts on the SanBusinessGroup 1C stock that the site has no card for
 * (Теплов и Сухов and the KD/DS parts): each becomes a complete card linked to its 1C row.
 *
 * - photos and category come from the closest card of the same element already on the site:
 *   same element type, same «термо/моно», same steel, nearest diameter; a row without such a
 *   card is reported, not created blind;
 * - specs come from the name: element, design, steel, thickness, diameters, length, angle;
 * - price follows the house rule: cost = 1C wholesale / 1.15, retail = cost × 1.40;
 *   a row without a 1C price gets «Цену уточняйте».
 *
 *   php artisan integration:create-onec-chimneys            # dry run
 *   php artisan integration:create-onec-chimneys --apply
 */
class CreateOneCChimneyCardsCommand extends Command
{
    protected $signature = 'integration:create-onec-chimneys
        {--source=onec : Integration source code}
        {--apply : Create and link the cards}
        {--fill-missing-photos : Give the chimney cards created from 1C without a photo the closest card\'s photos}
        {--clean-specs : Drop «Картинки: Array» and the name specs the maker states better on cards created from 1C}';

    protected $description = 'Create complete cards for the stainless chimney parts of the 1C stock';

    public const WHOLESALE_MARKUP = 1.15;

    public const RETAIL_MARKUP = 1.40;

    /** Stand, banner and other shop fittings that live in the chimney group. */
    private const NOT_FOR_SALE = ['баннер', 'стенд'];

    public function handle(IntegrationProductCreator $creator): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.onec_chimneys.source=0');

            return self::FAILURE;
        }
        if ($this->option('fill-missing-photos')) {
            return $this->fillMissingPhotos($source);
        }
        if ($this->option('clean-specs')) {
            return $this->cleanSpecs($source);
        }
        $items = IntegrationProduct::query()->where('integration_source_id', $source->id)->inStock()
            ->whereNull('product_id')->whereIn('match_status', ['unmatched', 'suggested', 'ambiguous'])
            ->with('integrationCategory:id,path')->get()
            ->filter(fn (IntegrationProduct $item) => str_contains((string) $item->integrationCategory?->path, 'Дымоходы (Нержавейка)'))
            ->values();
        $pool = $this->siblingPool();
        $brand = Brand::query()->where('name', 'like', 'Теплов%')->orderByDesc('is_active')->first();
        $categories = Category::query()->whereIn('slug', array_unique(array_merge(array_values(self::CATEGORY_BY_TYPE), ['prochie-dymohod'])))->pluck('id', 'slug');
        $rubRate = $this->rubRate();
        $this->line('metric.onec_chimneys.rub_rate_x10000='.(int) round($rubRate * 10000));

        $stats = ['rows' => $items->count(), 'not_for_sale' => 0, 'from_maker' => 0, 'from_sibling' => 0, 'no_source' => 0,
            'maker_price' => 0, 'maker_below_cost' => 0, 'rule_price' => 0, 'no_price' => 0, 'created' => 0, 'images_saved' => 0];
        foreach ($items as $item) {
            $name = (string) $item->name;
            if (Str::contains(mb_strtolower($name), self::NOT_FOR_SALE)) {
                $stats['not_for_sale']++;

                continue;
            }
            $isTis = str_contains((string) $item->integrationCategory?->path, 'Теплов и Сухов');
            $title = self::title($name, $isTis);
            // The maker's own page first: exact photo, description, specs and retail price.
            $url = $this->teplov->find($name);
            $page = $url ? $this->teplov->page($url) : null;
            $sibling = $page ? null : self::closestSibling($title, $pool);
            if (! $page && ! $sibling) {
                $stats['no_source']++;
                $this->line(sprintf('audit.onec_chimneys.no_source=%d:%s', $item->id, Str::slug(Str::limit($title, 70, ''))));

                continue;
            }
            $stats[$page ? 'from_maker' : 'from_sibling']++;

            $wholesale = (float) $item->normalizedPriceByn();
            [$retail, $priceFrom] = match (true) {
                // The maker's Russian retail, unless it would sell below our own wholesale + 5 %.
                $page && $page['price_rub'] && $rubRate > 0 && ($wholesale <= 0 || $page['price_rub'] * $rubRate >= $wholesale * 1.05)
                    => [round($page['price_rub'] * $rubRate, 2), 'maker_price'],
                $wholesale > 0 => [self::retailFromWholesale($wholesale), 'rule_price'],
                default => [0.0, 'no_price'],
            };
            $stats[$priceFrom]++;
            if ($page && $page['price_rub'] && $priceFrom !== 'maker_price') {
                $stats['maker_below_cost']++;
            }
            $specs = self::mergeSpecs(self::specs($title), $page ? self::makerSpecs($page['specs']) : []);
            $categorySlug = self::categorySlug($title);
            $this->line(sprintf('audit.onec_chimneys.plan=%d:%s:%s:%s:%d:%s', $item->id, Str::slug(Str::limit($title, 70, '')),
                $page ? 'maker' : 'sibling-'.$sibling->id, $categorySlug, (int) round($retail), $priceFrom));
            if (! $this->option('apply')) {
                continue;
            }

            $product = $creator->createAndLink($item, [
                'name' => $title,
                'h1' => $title,
                'category_id' => $categories[$categorySlug] ?? $categories['prochie-dymohod'] ?? $sibling?->category_id,
                'retail_price' => $retail,
                'brand_id' => $isTis ? $brand?->id : null,
                'images' => $page ? [] : array_values((array) $sibling->images),
                'specs' => collect($specs)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all(),
                'short_description' => $page && $page['description'] !== '' ? Str::limit($page['description'], 300, '…') : self::shortDescription($title, $specs),
                'content_source_url' => $url,
                'meta_title' => Str::limit($title, 60, '').' — купить в %city%',
                'meta_description' => Str::limit($title.'. Элемент дымохода из нержавеющей стали. Цена, наличие и доставка по Беларуси.', 175, ''),
            ]);
            if (! $product) {
                continue;
            }
            $product->forceFill(['requires_price_confirmation' => $retail <= 0])->save();
            $stats['created']++;
            if ($page && $page['images'] !== []) {
                $stats['images_saved'] += (int) (app(ProductSourceEnricher::class)->enrichFromParsed($product, $url, ['images' => []], [
                    'verified_images' => $page['images'], 'update_images' => true, 'update_specs' => false, 'update_service' => false,
                    'update_documents' => false, 'update_video' => false, 'update_content' => false,
                ])['images_saved'] ?? 0);
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_chimneys.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Element type and design → the chimney category of the site. */
    private const CATEGORY_BY_TYPE = [
        'труба|термо' => 'truby-sendvich', 'труба' => 'truby-mono',
        'тройник|термо' => 'troyniki-sendvich', 'тройник' => 'troyniki-mono',
        'отвод|термо' => 'kolena-sendvich', 'отвод' => 'kolena-mono',
        'конус' => 'perehody-adaptery-dymohod', 'переход' => 'perehody-adaptery-dymohod', 'адаптер' => 'perehody-adaptery-dymohod',
        'юбка' => 'perehody-adaptery-dymohod', 'конденсатоотвод' => 'kondensatootvody', 'заглушка' => 'zaglushki-dymohod',
        'зонт' => 'zonty-deflektory', 'дефлектор' => 'zonty-deflektory', 'шибер' => 'shibery-dymohod',
        'хомут' => 'krepleniya-dymohod', 'крепление' => 'krepleniya-dymohod', 'кронштейн' => 'krepleniya-dymohod',
        'площадка' => 'krepleniya-dymohod', 'лист' => 'krepleniya-dymohod', 'проход' => 'krepleniya-dymohod',
        'проходка' => 'krepleniya-dymohod', 'фартук' => 'krepleniya-dymohod',
    ];

    public function __construct(private readonly TeplovSiteCatalog $teplov)
    {
        parent::__construct();
    }

    public static function categorySlug(string $title): string
    {
        // «Титан 16», «ППШ-Б» are heat exchangers worn on the chimney pipe.
        if (preg_match('/титан|ппш/iu', $title)) {
            return 'teplosyomniki';
        }
        $signature = explode('|', LinkOneCByParametersCommand::signature($title));
        $type = $signature[0] ?? '';
        $termo = in_array('термо', $signature, true);

        return self::CATEGORY_BY_TYPE[$type.($termo ? '|термо' : '')] ?? self::CATEGORY_BY_TYPE[$type] ?? 'prochie-dymohod';
    }

    /** teplov.ru props as card specs: «Толщина изоляции: 40» → «Толщина изоляции: 40 мм». */
    /**
     * The maker's own specs win; from the name stay only what the maker does not state
     * (element, design, length, angle, clamp, colour).
     */
    public static function mergeSpecs(array $fromName, array $fromMaker): array
    {
        $fromMaker = array_filter($fromMaker, fn ($value, $key) => ! preg_match('/^картинк/iu', (string) $key) && ! str_contains((string) $value, 'Array'), ARRAY_FILTER_USE_BOTH);
        if ($fromMaker === []) {
            return $fromName;
        }
        $makerHas = mb_strtolower(implode(' ', array_keys($fromMaker)));
        foreach (['Марка стали' => 'марка стали', 'Толщина стали' => 'толщина металла', 'Внутренний диаметр' => 'диаметр', 'Внешний диаметр' => 'диаметр', 'Диаметр' => 'диаметр'] as $key => $needle) {
            if (str_contains($makerHas, $needle)) {
                unset($fromName[$key]);
            }
        }

        return array_merge($fromName, $fromMaker);
    }

    public static function makerSpecs(array $props): array
    {
        $specs = [];
        foreach ($props as $key => $value) {
            $unit = preg_match('/толщина|диаметр|длина|высота|ширина/iu', $key) && is_numeric(str_replace(',', '.', $value)) ? ' мм' : '';
            $specs[$key] = (preg_match('/марка стали/iu', $key) && ctype_digit($value) ? 'AISI ' : '').$value.$unit;
        }

        return $specs;
    }

    /**
     * teplov.ru serves some product photos as 404: those cards take the photos of the closest card
     * of the same element, like the parts the maker does not list.
     */
    private function fillMissingPhotos(IntegrationSource $source): int
    {
        $pool = $this->siblingPool();
        $products = Product::query()->whereIn('id', IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where('match_method', 'manual_created')->whereNotNull('product_id')->select('product_id'))
            // images is a JSON column: «= '[]'» never matches an empty array there.
            ->whereRaw('COALESCE(JSON_LENGTH(images), 0) = 0')
            ->get();
        $stats = ['without_photo' => $products->count(), 'filled' => 0, 'no_sibling' => 0];
        foreach ($products as $product) {
            if (self::categorySlug((string) $product->name) === 'prochie-dymohod' && ! preg_match('/дымоход|нерж|430|304/iu', (string) $product->name)) {
                continue;
            }
            $sibling = self::closestSibling((string) $product->name, $pool->where('id', '!=', $product->id));
            if (! $sibling) {
                $stats['no_sibling']++;
                $this->line(sprintf('audit.onec_chimneys.no_source=%d:%s', $product->id, Str::slug(Str::limit((string) $product->name, 70, ''))));

                continue;
            }
            $this->line(sprintf('audit.onec_chimneys.photo=%d:%s:%d', $product->id, Str::slug(Str::limit((string) $product->name, 70, '')), $sibling->id));
            if ($this->option('apply')) {
                $product->forceFill(['images' => array_values((array) $sibling->images)])->save();
                $stats['filled']++;
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_chimneys.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Specs read from the 1C name; every other key on these cards came from the maker's page. */
    private const NAME_SPEC_KEYS = ['Элемент дымохода', 'Исполнение', 'Марка стали', 'Толщина стали', 'Внутренний диаметр', 'Внешний диаметр',
        'Диаметр', 'Длина', 'Угол', 'Хомут в комплекте', 'Утеплитель', 'Цвет'];

    private function cleanSpecs(IntegrationSource $source): int
    {
        $products = Product::query()->whereIn('id', IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where('match_method', 'manual_created')->whereNotNull('product_id')->select('product_id'))->get();
        $stats = ['cards' => $products->count(), 'cleaned' => 0, 'attributes_removed' => 0];
        foreach ($products as $product) {
            $specs = collect((array) $product->specs)->mapWithKeys(fn ($spec) => [(string) ($spec['key'] ?? '') => (string) ($spec['value'] ?? '')])->all();
            $fromName = array_intersect_key($specs, array_flip(self::NAME_SPEC_KEYS));
            $fromMaker = array_diff_key($specs, $fromName);
            $clean = self::mergeSpecs($fromName, $fromMaker);
            $removed = array_keys(array_diff_key($specs, $clean));
            if ($removed === []) {
                continue;
            }
            $stats['cleaned']++;
            $this->line(sprintf('audit.onec_chimneys.cleaned=%d:%d', $product->id, count($removed)));
            if (! $this->option('apply')) {
                continue;
            }
            $product->forceFill(['specs' => collect($clean)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all()])->save();
            $stats['attributes_removed'] += \Illuminate\Support\Facades\DB::table('product_attribute_values')->where('product_id', $product->id)
                ->whereIn('attribute_id', \Illuminate\Support\Facades\DB::table('attributes')->where('category_id', $product->category_id)
                    ->whereIn('name', $removed)->select('id'))
                ->delete();
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_chimneys.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** BYN for one Russian rouble, from the National Bank of Belarus. */
    private function rubRate(): float
    {
        try {
            $rate = Http::timeout(15)->get('https://api.nbrb.by/exrates/rates/RUB?parammode=2')->json();

            return (float) ($rate['Cur_OfficialRate'] ?? 0) / max(1, (int) ($rate['Cur_Scale'] ?? 100));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public static function retailFromWholesale(float $wholesale): float
    {
        return round($wholesale / self::WHOLESALE_MARKUP * self::RETAIL_MARKUP, 2);
    }

    /** «KD HT D240 F050 Хомут в шахту d250мм нерж.ст.430-0,5мм» → «Хомут в шахту d250 мм нерж. ст. 430-0,5 мм»; ТиС rows get the brand. */
    public static function title(string $name, bool $tis): string
    {
        $title = trim((string) preg_replace('/^(?:[A-Z]{2,3}\s+)(?:[A-Z0-9>\/.]+\s+)*F\d{3}\s+/u', '', trim($name)));
        $title = str_replace(['45*', '87*', '90*', '15*', '30*'], ['45°', '87°', '90°', '15°', '30°'], $title);
        $title = (string) preg_replace('/(\d)мм\b/u', '$1 мм', $title);
        $title = (string) preg_replace('/\s+/u', ' ', $title);
        if ($tis && mb_stripos($title, 'теплов') === false) {
            $title = 'Теплов и Сухов '.$title;
        }

        return $title;
    }

    /** @return array<string,string> */
    public static function specs(string $title): array
    {
        $text = str_replace(',', '.', $title);
        $low = mb_strtolower($text);
        $type = (string) Str::before(LinkOneCByParametersCommand::signature($title), '|');
        $specs = ['Элемент дымохода' => Str::ucfirst($type ?: 'элемент дымохода')];
        if (preg_match('/\bтермо\b|ТТ-|КТ-|ОТ-|ТРТ-|ПМТ-|ЗРТ-/u', $text)) {
            $specs['Исполнение'] = 'Термо (сэндвич, утеплённый)';
        } elseif (preg_match('/\bмоно\b|ТМ-|КМ-|ОМ-|ЗРМ-|ПММ-|ШМ/u', $text)) {
            $specs['Исполнение'] = 'Моно (одностенный)';
        }
        if (preg_match('/\b(304|316|321|430|439)\s*-\s*(\d+(?:\.\d+)?)(?:\s*\/\s*(304|316|430|439))?/u', $text, $m)) {
            $specs['Марка стали'] = 'AISI '.$m[1].(isset($m[3]) && $m[3] !== '' ? ' / AISI '.$m[3].' (кожух)' : '');
            $specs['Толщина стали'] = $m[2].' мм';
        } elseif (preg_match('/(?:ст\.?|aisi)\s*(304|316|430|439)\s*[-\/]\s*(\d+(?:\.\d+)?)/iu', $text, $m)) {
            $specs['Марка стали'] = 'AISI '.$m[1];
            $specs['Толщина стали'] = $m[2].' мм';
        }
        if (preg_match('/[DdДдФф]\s*(\d{2,3})\s*\/\s*(\d{2,3})/u', $text, $m)) {
            $specs['Внутренний диаметр'] = $m[1].' мм';
            $specs['Внешний диаметр'] = $m[2].' мм';
        } elseif (preg_match('/[DdДдФф]\s*(\d{2,3})\s*[>-]\s*[DdДд]?\s*(\d{2,3})/u', $text, $m)) {
            $specs['Диаметр'] = $m[1].'–'.$m[2].' мм';
        } elseif (preg_match('/[DdДдФф]\s*(\d{2,3})\b/u', $text, $m)) {
            $specs['Диаметр'] = $m[1].' мм';
        }
        if (preg_match('/\bL\s*-?\s*(\d{3,4})\b/u', $text, $m)) {
            $specs['Длина'] = $m[1].' мм';
        }
        if (preg_match('/(\d{2})°/u', $text, $m)) {
            $specs['Угол'] = $m[1].'°';
        }
        if (str_contains($low, 'хомут') && ! str_starts_with($low, 'хомут') && $type !== 'хомут') {
            $specs['Хомут в комплекте'] = 'Да';
        }
        if (str_contains($low, 'керамоволокно')) {
            $specs['Утеплитель'] = 'Керамоволокно';
        }
        if (preg_match('/ч[её]рн/u', $low)) {
            $specs['Цвет'] = 'Чёрный';
        }

        return $specs;
    }

    private static function shortDescription(string $title, array $specs): string
    {
        $parts = array_filter([
            $specs['Исполнение'] ?? null,
            isset($specs['Марка стали']) ? 'сталь '.$specs['Марка стали'].(isset($specs['Толщина стали']) ? ', '.$specs['Толщина стали'] : '') : null,
            isset($specs['Внутренний диаметр']) ? 'диаметр '.$specs['Внутренний диаметр'].' / '.$specs['Внешний диаметр'] : ($specs['Диаметр'] ?? null ? 'диаметр '.$specs['Диаметр'] : null),
            isset($specs['Длина']) ? 'длина '.$specs['Длина'] : null,
        ]);

        return $title.($parts !== [] ? ': '.mb_strtolower(implode(', ', $parts)) : '').'.';
    }

    /** @return Collection<int,Product> chimney cards with photos to borrow from */
    private function siblingPool(): Collection
    {
        // Chimney parts by their marks, wherever their category is (ТиС cards sit in several).
        return Product::query()->where('is_archived', false)->where('is_active', true)
            ->whereNotNull('images')->where('images', '!=', '[]')
            ->where(fn ($q) => $q->where('name', 'like', '%Теплов%')->orWhere('name', 'like', '%ТИС%')
                ->orWhere('name', 'like', '%нерж%')->orWhere('name', 'like', '%430%')->orWhere('name', 'like', '%304%')
                ->orWhere('name', 'like', '%Термо%')->orWhere('name', 'like', '%Моно%'))
            ->whereHas('category', fn ($q) => $q->where('slug', 'not like', '%koaks%')->where('slug', 'not like', '%koaxial%'))
            ->with('category:id,slug,name')
            ->get(['id', 'name', 'slug', 'category_id', 'images']);
    }

    /** Same element type and design, then same steel, then the nearest diameter. */
    public static function closestSibling(string $title, Collection $pool): ?Product
    {
        $signature = explode('|', LinkOneCByParametersCommand::signature($title));
        $type = $signature[0] ?? '';
        if ($type === '') {
            return null;
        }
        $design = array_values(array_intersect($signature, ['термо', 'моно']));
        $steel = preg_match('/\b(304|316|430|439)\b/u', $title, $m) ? $m[1] : null;
        $diameter = preg_match('/[DdДдФф]\s*(\d{2,3})/u', $title, $d) ? (int) $d[1] : null;

        return $pool->map(function (Product $product) use ($type, $design, $steel, $diameter): ?array {
            $other = explode('|', LinkOneCByParametersCommand::signature((string) $product->name));
            if (($other[0] ?? '') !== $type || array_values(array_intersect($other, ['термо', 'моно'])) !== $design || empty($product->images)) {
                return null;
            }
            $score = 0;
            // The same maker first: a ТиС part borrows a ТиС photo.
            if (preg_match('/теплов|тис/iu', (string) $product->name)) {
                $score += 2000;
            }
            if ($steel !== null && preg_match('/\b'.$steel.'\b/u', (string) $product->name)) {
                $score += 1000;
            }
            if ($diameter !== null && preg_match('/[DdДдФф]\s*(\d{2,3})/u', (string) $product->name, $od)) {
                $score -= abs((int) $od[1] - $diameter);
            }

            return ['product' => $product, 'score' => $score];
        })->filter()->sortByDesc('score')->first()['product'] ?? null;
    }
}
