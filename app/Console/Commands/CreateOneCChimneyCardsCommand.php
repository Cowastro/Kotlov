<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
        {--apply : Create and link the cards}';

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
        $items = IntegrationProduct::query()->where('integration_source_id', $source->id)->inStock()
            ->whereNull('product_id')->whereIn('match_status', ['unmatched', 'suggested', 'ambiguous'])
            ->with('integrationCategory:id,path')->get()
            ->filter(fn (IntegrationProduct $item) => str_contains((string) $item->integrationCategory?->path, 'Дымоходы (Нержавейка)'))
            ->values();
        $pool = $this->siblingPool();
        $brand = Brand::query()->where('name', 'like', 'Теплов%')->orderByDesc('is_active')->first();

        $stats = ['rows' => $items->count(), 'not_for_sale' => 0, 'no_sibling' => 0, 'planned' => 0, 'created' => 0, 'with_price' => 0];
        foreach ($items as $item) {
            $name = (string) $item->name;
            if (Str::contains(mb_strtolower($name), self::NOT_FOR_SALE)) {
                $stats['not_for_sale']++;

                continue;
            }
            $isTis = str_contains((string) $item->integrationCategory?->path, 'Теплов и Сухов');
            $title = self::title($name, $isTis);
            $sibling = self::closestSibling($title, $pool);
            if (! $sibling) {
                $stats['no_sibling']++;
                $this->line(sprintf('audit.onec_chimneys.no_sibling=%d:%s', $item->id, Str::slug(Str::limit($title, 70, ''))));

                continue;
            }
            $wholesale = (float) $item->normalizedPriceByn();
            $retail = $wholesale > 0 ? self::retailFromWholesale($wholesale) : 0.0;
            $stats['with_price'] += (int) ($retail > 0);
            $stats['planned']++;
            $this->line(sprintf('audit.onec_chimneys.plan=%d:%s:%d:%s:%d', $item->id, Str::slug(Str::limit($title, 70, '')), $sibling->id,
                Str::slug((string) $sibling->category?->slug) ?: 'none', (int) round($retail)));
            if (! $this->option('apply')) {
                continue;
            }

            $specs = self::specs($title);
            $product = $creator->createAndLink($item, [
                'name' => $title,
                'h1' => $title,
                'category_id' => $sibling->category_id,
                'retail_price' => $retail,
                'brand_id' => $isTis ? $brand?->id : null,
                'images' => array_values((array) $sibling->images),
                'specs' => collect($specs)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all(),
                'short_description' => self::shortDescription($title, $specs),
                'meta_title' => Str::limit($title, 60, '').' — купить в %city%',
                'meta_description' => Str::limit($title.'. Элемент дымохода из нержавеющей стали. Цена, наличие и доставка по Беларуси.', 175, ''),
            ]);
            if ($product) {
                $product->forceFill(['requires_price_confirmation' => $retail <= 0])->save();
                $stats['created']++;
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_chimneys.{$key}={$value}");
        }

        return self::SUCCESS;
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
        return Product::query()->where('is_archived', false)->where('is_active', true)
            ->whereNotNull('images')->where('images', '!=', '[]')
            ->whereHas('category', fn ($q) => $q->where('slug', 'like', '%dymohod%')->orWhere('name', 'like', '%ымоход%'))
            ->with('category:id,slug,name')
            ->get(['id', 'name', 'category_id', 'images']);
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
