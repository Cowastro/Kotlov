<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * One-off, hand-verified retail price sync for brand «Царь-Печи», sourced
 * from Прайс_Царь-печи_31.08.2026.xlsx (sbg.by/ООО «СанБизнесГруп»).
 *
 * The price list identifies products by a 13-digit EAN-13 barcode that has
 * no counterpart column in kotlov.by's `products` table, and kotlov's own
 * SKUs (PS-0XX.XXX) carry no relation to it either — so there is no safe
 * automatic join key. Every row below was matched by hand against the full
 * kotlov.by "Царь-Печи" catalog (75 products, see catalog:export-prices
 * --brand=Царь --include-archived) comparing model name + thickness + tier
 * (Премиум/Комфорт/Элит/Декор/ЧДС). Deliberately left unmatched:
 *  - the whole "10 мм" thickness sub-line (19 products) — absent from this
 *    price list entirely;
 *  - "МБТ-1/2", "ММТ-1/2" (4 products) — no counterpart in the price list;
 *  - "Вольга 1", "Вольга 2" (old numbered variants) — no counterpart;
 *  - "Вольга Элит (8 мм)" (id 9956) — likely a duplicate of "Царь-Вольга
 *    (8 мм)" (id 9955, matched below) under a different name; not touched;
 *  - product id 9928 "...Буржуйка мини с конфоркой" — near-duplicate of id
 *    9929 "...Буржуйка мини с комфоркой (8 мм)" (matched below, same price
 *    in the price list either way); not touched, needs manual dedup review;
 *  - 18 price-list rows (10 accessories, 3 мангала, "Добрыня Короб", 3 ППК
 *    "под казан", "Горелка пеллетная Буржуйка") have no matching product in
 *    kotlov.by's Царь-Печи catalog at all — likely not yet listed.
 *
 * Matched products are un-archived/activated (with user's explicit
 * confirmation) and their price set to the price list's "Розница" column,
 * rounded to the nearest whole BYN to match kotlov.by's existing convention
 * for this brand (all current prices are whole numbers).
 *
 *   php artisan supplier:sync-tsarpechi-retail            # dry run
 *   php artisan supplier:sync-tsarpechi-retail --apply
 */
class SyncTsarPechiRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-tsarpechi-retail {--apply : Write price + un-archive/activate changes}';

    protected $description = 'Hand-verified retail price sync + un-archive for brand «Царь-Печи» from Прайс_Царь-печи_31.08.2026.xlsx';

    /** @var array<int,int> product id => new retail price (BYN, rounded) */
    private const MAP = [
        9906 => 1099, // Василиса (8мм)
        5312 => 1390, // Горыня (8мм)
        9907 => 2066, // Добрыня (8мм)
        5313 => 1209, // Забава (8мм)
        5887 => 1280, // Затея (8мм)
        9908 => 1396, // Любаня (8мм)
        9945 => 1209, // Забава Декор (8мм)
        11982 => 791, // Емеля Лайт (3мм)
        11981 => 703, // Емеля мини Лайт (3мм)
        9946 => 1456, // Вольга (8мм)
        5888 => 2352, // Добрыня 1 (8мм)
        9947 => 2626, // Добрыня 2 (8мм)
        9948 => 1363, // Елисей (8мм)
        9949 => 835,  // Емеля мини (8мм)
        9950 => 1187, // Емеля ПЛЮС (8мм)
        9951 => 934,  // Емеля (8мм)
        9952 => 1478, // Любаня Комфорт (8мм)
        9953 => 1033, // Малуша (8мм)
        9954 => 1566, // Святогор (8мм)
        9958 => 2659, // Вольга Премиум (8мм)
        9959 => 2538, // Горыня Премиум (8мм)
        9960 => 4044, // Добрыня Премиум (8мм)
        9961 => 2505, // Елисей Премиум (8мм)
        9962 => 1742, // Емеля Премиум (8мм)
        9963 => 2275, // Забава Премиум (8мм)
        9964 => 2407, // Любаня Премиум (8мм)
        9965 => 2835, // Святогор Премиум (8мм)
        9955 => 1879, // Царь-Вольга (8мм)
        9957 => 1753, // Царь-Забава (8мм)
        9929 => 747,  // Буржуйка мини с комфоркой (8мм)
        9930 => 659,  // Буржуйка мини (8мм)
        9931 => 802,  // Буржуйка с конфоркой (8мм)
        9932 => 747,  // Буржуйка (8мм)
        5624 => 703,  // Злата (8мм)
        5797 => 1022, // Золовка (8мм)
        9933 => 1857, // Матрешка Большая 1 ЧДС (8мм)
        9935 => 1330, // Матрешка Большая 1 (8мм)
        9934 => 1978, // Матрешка Большая 2 ЧДС (8мм)
        9936 => 1456, // Матрешка Большая 2 (8мм)
        9937 => 1456, // Матрешка Малая 1 ЧДС (8мм)
        5321 => 1187, // Матрешка малая 1 (8мм)
        9938 => 1582, // Матрешка Малая 2 ЧДС (8мм)
        7224 => 1275, // Матрешка малая 2 (8мм)
        8881 => 703,  // Милана (8мм)
        9939 => 703,  // Ярило Декор (6мм в карточке / 8мм в прайсе — та же модель)
        9940 => 648,  // Ярило (8мм)
        12112 => 165, // Пеллетная горелка для печи Потапыч
        12111 => 297, // Отопительная печь Потапыч (3мм)
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = array_keys(self::MAP);

        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');

        $missing = array_diff($ids, $products->keys()->all());
        if (! empty($missing)) {
            $this->warn('Не найдены в БД id: ' . implode(', ', $missing));
        }

        $priceChanged = 0;
        $unarchived = 0;
        $unchanged = 0;

        foreach (self::MAP as $id => $newPrice) {
            $product = $products->get($id);
            if (! $product) {
                continue;
            }

            $oldPrice = (float) $product->price;
            $wasArchived = (bool) $product->is_archived;
            $wasInactive = ! $product->is_active;

            $priceDiffers = abs($oldPrice - $newPrice) >= 0.01;
            $needsUnarchive = $wasArchived || $wasInactive;

            if (! $priceDiffers && ! $needsUnarchive) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '%s id=%d %s: price %s -> %s%s',
                $priceDiffers ? '~' : '=',
                $id,
                $product->sku,
                number_format($oldPrice, 2),
                number_format($newPrice, 2),
                $needsUnarchive ? ' [un-archive + activate]' : ''
            ));

            if ($priceDiffers) {
                $priceChanged++;
            }
            if ($needsUnarchive) {
                $unarchived++;
            }

            if ($apply) {
                $product->price = $newPrice;
                $product->is_archived = false;
                $product->is_active = true;
                $product->save();
            }
        }

        $this->info(sprintf(
            '%s matched=%d price_changed=%d unarchived=%d unchanged=%d',
            $apply ? 'APPLIED' : 'DRY-RUN',
            count(self::MAP),
            $priceChanged,
            $unarchived,
            $unchanged
        ));

        return self::SUCCESS;
    }
}
