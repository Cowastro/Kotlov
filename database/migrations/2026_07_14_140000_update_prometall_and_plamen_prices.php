<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retail prices from "Прайс-лист 07.07.2026.xlsx".
     *
     * ProMETALL rows are matched by model/variant against the PROMETALL sheet.
     * Plamen rows are matched by model against the PLAMEN sheet. The catalog
     * currently contains two Barun 1 cards, so both receive the same price.
     */
    private const NEW_PRICES = [
        // Plamen
        'PS-007.448' => 3400.00, // Barun 1
        'PS-008.736' => 3400.00, // Barun 1 (duplicate catalog card)
        'PS-007.449' => 4800.00, // Barun Insert Termo
        'PS-007.453' => 3740.00, // Glas Franklin
        'PS-007.454' => 3280.00, // Vesta Insert
        'PS-007.568' => 3950.00, // Tena N
        'PS-007.455' => 5800.00, // Tena Termo
        'PS-007.569' => 3250.00, // Amity 3 N
        'PS-011.774' => 4050.00, // Alberto
        'PS-007.462' => 3500.00, // Laguna
        'PS-007.458' => 5000.00, // Nera N
        'PS-010.067' => 3600.00, // Gala
        'PS-010.066' => 3750.00, // Aria
        'PS-010.547' => 2390.00, // Tara
        'PS-007.571' => 5290.00, // Termo Glas N
        'PS-007.572' => 4440.00, // Plamen 850 Glas N
        'PS-007.573' => 4440.00, // Slavonac N

        // ProMETALL
        'PS-011.203' => 3770.00, // Атмосфера M, сетка пруток
        'PS-011.204' => 3269.00, // Атмосфера M, сетка нержавейка
        'PS-011.206' => 5221.00, // Атмосфера L, сетка пруток (портал Эйфория)
        'PS-011.207' => 4463.00, // Атмосфера L, сетка нержавейка
        'PS-011.208' => 4913.00, // Атмосфера XL, сетка нержавейка
        'PS-011.209' => 7735.00, // Атмосфера L, Окаменевшее дерево
        'PS-011.210' => 6684.00, // Атмосфера L, Змеевик наборный
        'PS-011.221' => 1845.00, // Бахта, чёрная
        'PS-011.246' => 1421.00, // Бахтинка
        'PS-011.247' => 3154.00, // Маэстро
        'PS-011.494' => 1075.00, // Конвектор Лира 130М-115М, чёрный
        'PS-011.692' => 1152.00, // Тайга ПРО
        'PS-011.693' => 151.00,  // Полки для подогрева
        'PS-012.277' => 3154.00, // Маэстро II
        'KOTLOV-004649' => 3770.00, // Активная Атмосфера M, сетка пруток (p0503)
    ];

    private const OLD_PRICES = [
        'PS-007.448' => 3040.00,
        'PS-008.736' => 3400.00,
        'PS-007.449' => 4800.00,
        'PS-007.453' => 3580.00,
        'PS-007.454' => 3280.00,
        'PS-007.568' => 3950.00,
        'PS-007.455' => 5800.00,
        'PS-007.569' => 3250.00,
        'PS-011.774' => 4050.00,
        'PS-007.462' => 3500.00,
        'PS-007.458' => 5000.00,
        'PS-010.067' => 3600.00,
        'PS-010.066' => 3750.00,
        'PS-010.547' => 1999.00,
        'PS-007.571' => 5290.00,
        'PS-007.572' => 4440.00,
        'PS-007.573' => 4500.00,
        'PS-011.203' => 3520.00,
        'PS-011.204' => 3310.00,
        'PS-011.206' => 4060.00,
        'PS-011.207' => 4130.00,
        'PS-011.208' => 4540.00,
        'PS-011.209' => 7320.00,
        'PS-011.210' => 6290.00,
        'PS-011.221' => 1690.00,
        'PS-011.246' => 1300.00,
        'PS-011.247' => 0.00,
        'PS-011.494' => 1000.00,
        'PS-011.692' => 975.00,
        'PS-011.693' => 130.00,
        'PS-012.277' => 2887.00,
        'KOTLOV-004649' => 3565.00,
    ];

    private const NEW_SUPPLIER_PRICES = [
        ['supplier_code' => 'bania', 'article' => 'p0503', 'price' => 2827.50],
    ];

    private const OLD_SUPPLIER_PRICES = [
        ['supplier_code' => 'bania', 'article' => 'p0503', 'price' => 2892.36],
    ];

    public function up(): void
    {
        $this->updatePrices(self::NEW_PRICES);
        $this->updateSupplierPrices(self::NEW_SUPPLIER_PRICES);
    }

    public function down(): void
    {
        $this->updatePrices(self::OLD_PRICES);
        $this->updateSupplierPrices(self::OLD_SUPPLIER_PRICES);
    }

    private function updatePrices(array $prices): void
    {
        DB::transaction(function () use ($prices): void {
            foreach ($prices as $sku => $price) {
                DB::table('products')
                    ->where('sku', $sku)
                    ->update([
                        'price' => $price,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function updateSupplierPrices(array $prices): void
    {
        DB::transaction(function () use ($prices): void {
            foreach ($prices as $row) {
                $supplierId = DB::table('suppliers')
                    ->where('code', $row['supplier_code'])
                    ->value('id');

                if (! $supplierId) {
                    continue;
                }

                DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('supplier_article', $row['article'])
                    ->update([
                        'price' => $row['price'],
                        'price_byn' => $row['price'],
                        'updated_at' => now(),
                    ]);
            }
        });
    }
};
