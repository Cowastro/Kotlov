<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Links in-stock 1C rows to a matcher candidate only when every parameter in both names is the
 * same: element type, steel grades, thickness, diameters, length, angle, «моно/термо», clamp.
 * «Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250 с хомутом» and the same pipe in D120/200 differ
 * in one number and must never be linked to each other. A row is linked when exactly one
 * candidate has its signature.
 *
 *   php artisan integration:link-onec-by-parameters            # dry run
 *   php artisan integration:link-onec-by-parameters --apply
 */
class LinkOneCByParametersCommand extends Command
{
    protected $signature = 'integration:link-onec-by-parameters
        {--source=onec : Integration source code}
        {--apply : Link the rows whose parameters match one candidate exactly}';

    protected $description = 'Link 1C rows to candidates whose names have exactly the same parameters';

    private const TYPES = [
        'конденсатоотвод', 'кондесатоотвод', 'тройник', 'отвод', 'колено', 'труба', 'конус', 'заглушка', 'площадка', 'переход', 'дефлектор',
        'зонт', 'хомут', 'кронштейн', 'шибер', 'юбка', 'проходка', 'старт', 'адаптер', 'лента', 'дверка', 'печь', 'котел', 'котёл', 'лист', 'одеяло', 'плита',
    ];

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.onec_params.source=0');

            return self::FAILURE;
        }
        $items = IntegrationProduct::query()->where('integration_source_id', $source->id)->inStock()
            ->whereNull('product_id')->whereIn('match_status', ['unmatched', 'suggested', 'ambiguous'])->get();

        $stats = ['rows' => 0, 'with_candidates' => 0, 'exact_one' => 0, 'exact_many' => 0, 'differs' => 0, 'linked' => 0];
        foreach ($items as $item) {
            $stats['rows']++;
            $ids = collect($item->candidates ?? [])->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                continue;
            }
            $stats['with_candidates']++;
            $signature = self::signature((string) $item->name);
            $products = Product::query()->whereIn('id', $ids)->where('is_archived', false)->get(['id', 'name', 'slug']);
            $exact = $products->filter(fn (Product $product) => $signature !== '' && self::signature((string) $product->name) === $signature)->values();

            if ($exact->count() === 1) {
                $stats['exact_one']++;
                $product = $exact->first();
                $this->line(sprintf('audit.onec_params.link=%d:%s:%d:%s', $item->id, Str::slug(Str::limit((string) $item->name, 60, '')), $product->id, $product->slug));
                if ($this->option('apply') && $decision->linkSelectedProduct($item, $product->id)) {
                    $stats['linked']++;
                }
            } elseif ($exact->count() > 1) {
                $stats['exact_many']++;
                $this->line(sprintf('audit.onec_params.many=%d:%s:%s', $item->id, Str::slug(Str::limit((string) $item->name, 60, '')), $exact->pluck('id')->implode(',')));
            } else {
                $stats['differs']++;
                $best = $products->first();
                $this->line(sprintf('audit.onec_params.differs=%d:%s:%d:%s', $item->id, Str::slug(Str::limit((string) $item->name, 60, '')),
                    (int) $best?->id, Str::slug(Str::limit((string) $best?->name, 60, '')) ?: 'none'));
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_params.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /**
     * Every number and parameter word of a name, normalised and sorted, plus the element type:
     * «Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250 с хомутом» → «труба|термо|хомут|0.8|1000|150|250|304|304».
     */
    public static function signature(string $name): string
    {
        $text = mb_strtolower(str_replace(['ё', ','], ['е', '.'], $name));
        // «150х250», «150×250»: a multiplication sign only between digits («хомут» keeps its «х»).
        $text = preg_replace('/(?<=\d)\s*[×хx]\s*(?=\d)/u', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=\d)\s*мм\b/u', '', $text) ?? $text;
        $type = collect(self::TYPES)->first(fn (string $word) => str_contains($text, $word)) ?? '';
        $type = match ($type) { 'кондесатоотвод' => 'конденсатоотвод', 'колено' => 'отвод', 'котёл' => 'котел', default => $type };
        if ($type === '') {
            return '';
        }
        $flags = array_values(array_filter([
            preg_match('/\bтермо\b|\bтт-|\bкт-|\bот-|\bпмт|\bтрт|\bзрт/u', $text) ? 'термо' : null,
            preg_match('/\bмоно\b|\bтм-|\bкм-|\bзрм|\bпмм/u', $text) ? 'моно' : null,
            str_contains($text, 'хомут') ? 'хомут' : null,
            preg_match('/\bревизи/u', $text) && $type !== 'заглушка' ? 'ревизия' : null,
            preg_match('/черн/u', $text) ? 'черный' : null,
        ]));
        preg_match_all('/\d+(?:\.\d+)?/u', $text, $numbers);
        // «0.80» = «0.8», but «1000» stays «1000».
        $numbers = array_map(fn (string $n) => str_contains($n, '.') ? (rtrim(rtrim($n, '0'), '.') ?: '0') : (ltrim($n, '0') ?: '0'), $numbers[0]);
        sort($numbers, SORT_STRING);

        return implode('|', array_merge([$type], $flags, $numbers));
    }
}
