<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ЭкоКамин promotion until 31.10.2026: −20% on every Дельта firebox and the
 * Мадрид, Рио, Крит, Эклипс, Родос, Прага fireplaces, −15% on Мюнхен and
 * Три стекла. The regular price moves to price_old; after the end date
 * catalog:expire-sale-campaigns restores it. Re-running never discounts twice.
 *
 *   php artisan catalog:apply-ecokamin-october-sale            # dry run
 *   php artisan catalog:apply-ecokamin-october-sale --apply
 */
class ApplyEcokaminOctoberSaleCommand extends Command
{
    protected $signature = 'catalog:apply-ecokamin-october-sale {--apply : Set the promotional prices}';

    protected $description = 'ЭкоКамин October 2026 promotion: −20% Дельта and six fireplace series, −15% Мюнхен and Три стекла';

    public const CAMPAIGN = 'ecokamin-2026-10';

    public const ENDS_AT = '2026-10-31 23:59:59';

    /** discount percent => series name patterns (lower case, ё → е) */
    public const RULES = [
        20 => ['дельта', 'delta', 'мадрид', 'madrid', 'рио', 'rio', 'крит', 'krit', 'эклипс', 'eclipse', 'eklips', 'родос', 'rodos', 'прага', 'praga'],
        15 => ['мюнхен', 'munchen', 'munich', 'три стекла'],
    ];

    /** Parts and accessories of these series are not part of the promotion. */
    private const EXCLUDED_WORDS = ['решетк', 'решётк', 'стекло ', 'дверц', 'облицовк', 'шибер', 'дымоход', 'труба', 'кожух', 'подставк', 'комплект', 'уплотн', 'ручк', 'колосник', 'шнур'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $brandIds = Brand::query()->where('slug', 'like', 'ecokamin%')->orWhere('name', 'ЭкоКамин')->orWhere('name', 'Ecokamin')->pluck('id');
        $endsAt = CarbonImmutable::parse(self::ENDS_AT, 'Europe/Minsk')->utc();
        $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);
        $stats = ['below_min_margin' => 0, 'matched' => 0, 'to_discount' => 0, 'already_in_campaign' => 0, 'skipped_no_price' => 0, 'excluded_parts' => 0, 'discounted' => 0];

        $plan = [];
        foreach (Product::query()->whereIn('brand_id', $brandIds)->where('is_active', true)->where('is_archived', false)->orderBy('id')->get() as $product) {
            $percent = $this->percentFor($product->name);
            if ($percent === null) {
                continue;
            }
            if ($this->isPart($product->name)) {
                $stats['excluded_parts']++;
                $this->line(sprintf('audit.ecokamin_sale.part=%d:%s', $product->id, $product->slug));

                continue;
            }
            $stats['matched']++;
            if ($product->sale_campaign === self::CAMPAIGN) {
                $stats['already_in_campaign']++;
                $this->line(sprintf('audit.ecokamin_sale.item=%d:%s:%d:%d:%d:active', $product->id, $product->slug, $percent, (int) $product->price_old, (int) $product->price));

                continue;
            }
            $regular = (float) $product->price;
            if ($regular <= 0 || $product->requires_price_confirmation) {
                $stats['skipped_no_price']++;

                continue;
            }
            $promo = round($regular * (100 - $percent) / 100);
            // Same cost basis as catalog:enforce-price-integrity-safety (stocked supplier rows).
            $cost = (float) DB::table('supplier_products')->where('product_id', $product->id)->where('stock_quantity', '>', 0)->where('price_byn', '>', 0)->min('price_byn');
            $margin = $cost > 0 ? (int) floor(($promo - $cost) * 100 / $promo) : null;
            if ($margin !== null && $margin < $minimumMargin) {
                $stats['below_min_margin']++;
            }
            $stats['to_discount']++;
            $plan[] = [$product, $regular, $promo];
            $this->line(sprintf('audit.ecokamin_sale.item=%d:%s:%d:%d:%d:new:%s', $product->id, $product->slug, $percent, (int) round($regular), (int) $promo, $margin === null ? 'nocost' : 'm'.max(-99, $margin)));
        }

        if ($apply && $plan !== []) {
            DB::transaction(function () use ($plan, $endsAt, &$stats): void {
                foreach ($plan as [$product, $regular, $promo]) {
                    $product->forceFill([
                        'price_old' => $regular,
                        'price' => $promo,
                        'is_sale' => true,
                        'sale_campaign' => self::CAMPAIGN,
                        'sale_ends_at' => $endsAt,
                    ])->save();
                    $stats['discounted']++;
                }
            });
            Cache::forget('home:data:v5');
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.ecokamin_sale.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: цены не изменены. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    public function percentFor(string $name): ?int
    {
        $text = ' '.mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name)).' ';
        foreach (self::RULES as $percent => $series) {
            // Мюнхен and Три стекла are fireplaces; «три стекла» on a firebox is only its glazing.
            if ($percent === 15 && str_contains($text, 'топк')) {
                continue;
            }
            foreach ($series as $word) {
                if (preg_match('/(?<!\p{L})'.preg_quote($word, '/').'(?!\p{L})/u', $text) === 1) {
                    return $percent;
                }
            }
        }

        return null;
    }

    private function isPart(string $name): bool
    {
        $text = mb_strtolower($name).' ';
        foreach (self::EXCLUDED_WORDS as $word) {
            if (str_starts_with($text, $word) || str_contains($text, ' '.$word)) {
                return ! str_contains($text, 'три стекла');
            }
        }

        return false;
    }
}
