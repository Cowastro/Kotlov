<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * One-off, hand-verified retail price sync for brand ComfortProm (печи
 * банные), sourced from «ПРАЙС КомфортПром ОПТ печи, мангалы 10,08,2026.xlsx»
 * (sheet «✔✔✔ Прайс печи 10,08,2026», column РРЦ с НДС).
 *
 * kotlov.by's ComfortProm catalog carries no article-code column matching
 * the price list's own codes (s3dog14, ch8ds20zk, ...), and product names
 * mix two conventions — legacy "РЕТРО ..." wording and the current
 * "Банная печь ComfortProm ... (до N м. куб.) ..." wording — confirmed by
 * the user to refer to the same current product line ("Ретро это старое
 * название, сейчас просто комфорт пром"). So every row below was matched
 * by hand on decoded features: материал (сталь/чугун), толщина (3/8 мм),
 * объём (14/16/20/26 кубов), закрытая каменка (да/нет), тип двери
 * (огонёк/чугунная/чугунная-со-стеклом/со-стеклом-гладкая/декор/панорама).
 *
 * 8 products name no door type at all ("РЕТРО СТАЛЬ 8 мм 20 м3") — mapped
 * to the base door for their thickness: чугунная (opaque) for 8 мм, ОГОНЕК
 * for 3 мм (the only no-glass style the price list carries at 3 мм).
 *
 * 15 products say only "дверь со стеклом" with no further qualifier, which
 * is genuinely ambiguous in the new price list between two distinct SKUs:
 * "чугунная дверь со стеклом" (cast door, small window) and "дверь со
 * стеклом (гладкая)" (steel door, large window). Resolved by opening the
 * live product photos on kotlov.by (e.g. 16-159382_*.png, 16-159455_*.png)
 * — both steel- and cast-body items photographed under this bare name show
 * the same flat steel door with a large square glass window — matching the
 * "гладкая" variant, which the user also confirmed by description
 * ("со стальной дверкой со стеклом"). All 15 mapped to the *dss/*dss*zk
 * codes accordingly. The one product whose name explicitly says "чугунная
 * дверь со стеклом" (id 21461) is unaffected by this and keeps the ds code.
 *
 * id 21461 has a real 17% jump (1145 -> 1340) — confirmed by the user to
 * apply as-is rather than treated as a data error.
 *
 *   php artisan supplier:sync-comfortprom-retail            # dry run
 *   php artisan supplier:sync-comfortprom-retail --apply
 */
class SyncComfortpromRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-comfortprom-retail {--apply : Write price changes}';

    protected $description = 'Hand-verified retail price sync for brand ComfortProm from ПРАЙС КомфортПром ОПТ печи, мангалы 10,08,2026.xlsx';

    /** @var array<int,int> product id => new retail price (BYN, rounded) */
    private const MAP = [
        21414 => 900,  // РЕТРО СТАЛЬ 8 мм 20 м3 -> s8dg20
        21415 => 820,  // РЕТРО СТАЛЬ 8 мм 16 м3 -> s8dg16
        21416 => 820,  // РЕТРО СТАЛЬ 8 мм стекло 16 м3 -> s8dss16
        21417 => 1330, // РЕТРО ПРЕМИУМ ЧУГУН стекло 26 м3 -> ch8dss26
        21418 => 900,  // РЕТРО СТАЛЬ 8 мм стекло 20 м3 -> s8dss20
        21419 => 1040, // РЕТРО СТАЛЬ 8 мм панорама 16 м3 -> s8dp16
        21420 => 1610, // РЕТРО ЧУГУН закрытая каменка панорама 20 м3 -> ch8dp20zk
        21421 => 1195, // РЕТРО ЧУГУН стекло 20 м3 -> ch8dss20
        21422 => 1195, // РЕТРО ЧУГУН 20 м3 -> ch8dg20
        21423 => 960,  // РЕТРО СТАЛЬ 8 мм 26 м3 -> s8dg26
        21424 => 1160, // сталь 8 мм закрытая каменка (26) дверь со стеклом -> s8dss26zk
        21425 => 1330, // РЕТРО ПРЕМИУМ ЧУГУН 26 м3 -> ch8dg26
        21426 => 960,  // РЕТРО СТАЛЬ 8 мм стекло 26 м3 -> s8dss26
        21427 => 1550, // РЕТРО ПРЕМИУМ ЧУГУН закрытая каменка стекло 26 м3 -> ch8dss26zk
        21428 => 1550, // РЕТРО ПРЕМИУМ ЧУГУН закрытая каменка 26 м3 -> ch8dg26zk
        21429 => 1080, // РЕТРО СТАЛЬ 8 мм закрытая каменка стекло 20 м3 -> s8dss20zk
        21430 => 1585, // премиум чугун закрытая каменка (26) дверь со стеклом декор -> ch8ddec26zk
        21431 => 1350, // РЕТРО СТАЛЬ 8 мм закрытая каменка панорама 20 м3 -> s8dp20zk
        21432 => 960,  // сталь 8 мм (26) дверь со стеклом -> s8dss26
        21433 => 820,  // сталь 8 мм (16) дверь со стеклом -> s8dss16
        21434 => 1405, // РЕТРО ЧУГУН закрытая каменка 20 м3 -> ch8dg20zk
        21435 => 1405, // РЕТРО ЧУГУН панорама 20 м3 -> ch8dp20
        21436 => 920,  // сталь 8 мм (20) дверь со стеклом декор -> s8ddec20
        21437 => 1150, // РЕТРО СТАЛЬ 8 мм закрытая каменка 26 м3 -> s8dg26zk
        21438 => 1740, // РЕТРО ПРЕМИУМ ЧУГУН закрытая каменка панорама 26 м3 -> ch8dp26zk
        21439 => 1405, // РЕТРО ЧУГУН закрытая каменка стекло 20 м3 -> ch8dss20zk
        21440 => 570,  // сталь 3 мм (14) стальная дверь огонек -> s3dog14
        21441 => 1080, // РЕТРО СТАЛЬ 8 мм закрытая каменка 20 м3 -> s8dg20zk
        21442 => 980,  // сталь 8 мм (26) дверь со стеклом декор -> s8ddec26
        21443 => 1260, // РЕТРО СТАЛЬ 8 мм панорама 26 м3 -> s8dp26
        21444 => 1170, // РЕТРО СТАЛЬ 8 мм панорама 20 м3 -> s8dp20
        21445 => 1445, // РЕТРО СТАЛЬ 8 мм закрытая каменка панорама 26 м3 -> s8dp26zk
        21446 => 1160, // РЕТРО СТАЛЬ 8 мм закрытая каменка стекло 26 м3 -> s8dss26zk
        21447 => 1560, // РЕТРО ПРЕМИУМ ЧУГУН панорама 26 м3 -> ch8dp26
        21448 => 630,  // РЕТРО СТАЛЬ 3 мм стекло 16 м3 -> s3dss14 (only 3мм glass sku)
        21449 => 845,  // сталь 8 мм (16) дверь со стеклом декор -> s8ddec16
        21450 => 695,  // сталь 3 мм (20) стальная дверь огонек -> s3dog20
        21451 => 615,  // сталь 3 мм (16) стальная дверь огонек -> s3dog16
        21452 => 1550, // премиум чугун закрытая каменка (26) дверь со стеклом -> ch8dss26zk
        21453 => 1430, // чугун закрытая каменка (20) дверь со стеклом декор -> ch8ddec20zk
        21454 => 1405, // чугун закрытая каменка (20) дверь со стеклом -> ch8dss20zk
        21455 => 1200, // сталь 8 мм закрытая каменка (26) дверь со стеклом декор -> s8ddec26zk
        21456 => 1100, // сталь 8 мм закрытая каменка (20) дверь со стеклом декор -> s8ddec20zk
        21457 => 1080, // сталь 8 мм закрытая каменка (20) дверь со стеклом -> s8dss20zk
        21458 => 1380, // премиум чугун (26) дверь со стеклом декор -> ch8ddec26
        21459 => 1330, // премиум чугун (26) дверь со стеклом -> ch8dss26
        21460 => 1220, // чугун 8-9 мм (20) чугунная дверь со стеклом декор -> ch8ddec20
        21461 => 1340, // чугун 8-9 мм (20) чугунная дверь со стеклом -> ch8ds20 (explicit "чугунная", not ambiguous)
        21462 => 900,  // сталь 8 мм (20) дверь со стеклом -> s8dss20
        21463 => 615,  // РЕТРО СТАЛЬ 3 мм 16 м3 -> s3dog16
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
        $unchanged = 0;

        foreach (self::MAP as $id => $newPrice) {
            $product = $products->get($id);
            if (! $product) {
                continue;
            }

            $oldPrice = (float) $product->price;
            $priceDiffers = abs($oldPrice - $newPrice) >= 0.01;

            if (! $priceDiffers) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '~ id=%d %s: price %s -> %s',
                $id,
                $product->sku,
                number_format($oldPrice, 2),
                number_format($newPrice, 2)
            ));

            $priceChanged++;

            if ($apply) {
                $product->price = $newPrice;
                $product->save();
            }
        }

        $this->info(sprintf(
            '%s matched=%d price_changed=%d unchanged=%d',
            $apply ? 'APPLIED' : 'DRY-RUN',
            count(self::MAP),
            $priceChanged,
            $unchanged
        ));

        return self::SUCCESS;
    }
}
