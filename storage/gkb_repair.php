<?php
// GKB supplier_products repair: fix wrong series cross-matches.
// Run: php artisan tinker --execute="require '/var/www/h209767/data/www/new.kotlov.by/storage/gkb_repair.php';"

$sid = DB::table('suppliers')->where('code', 'gazkotelbel')->value('id');
$now = now();
$ok = 0;

// 1. Fix АТЕМ Ж-3 products that got wrong ТУРБО or Ж10 articles
// Format: product_id => [correct_article, cost_byn, qty]
$updates = [
    623  => ['Ж3-КС-Г-010СН', 1270.00, 186],
    624  => ['Ж3-КС-Г-012СН', 1390.00, 163],
    628  => ['Ж3-КС-Г-015СН', 1515.00,  42],
    632  => ['Ж3-КС-Г-020СН', 1690.00,  17],
    2524 => ['Ж3-КС-Г-025СН', 1975.00,  16],
    635  => ['Ж3-КС-Г-030СН', 2260.00,   5],
];
foreach ($updates as $productId => [$article, $cost, $qty]) {
    $rows = DB::table('supplier_products')
        ->where('supplier_id', $sid)->where('product_id', $productId)
        ->update([
            'supplier_article'     => $article,
            'price'                => $cost,
            'price_byn'            => $cost,
            'in_stock'             => $qty > 0,
            'stock_quantity'       => $qty,
            'stock_status'         => $qty > 0 ? 'in_stock' : 'out_of_stock',
            'updated_at'           => $now,
        ]);
    echo ($rows ? 'UPDATED' : 'NOT FOUND') . " [$productId] → $article\n";
    $ok += $rows;
}

// 2. Move Ж3-КС-ГВ-012СН from KOTLOV [17169] to ATEM [627], archive KOTLOV
$sku627 = DB::table('products')->where('id', 627)->value('sku');
$moved = DB::table('supplier_products')
    ->where('supplier_id', $sid)->where('product_id', 17169)
    ->update(['product_id' => 627, 'product_sku' => $sku627, 'updated_at' => $now]);
if ($moved) {
    DB::table('products')->where('id', 17169)->update(['is_archived' => true, 'is_active' => false, 'updated_at' => $now]);
    echo "MOVED Ж3-КС-ГВ-012СН: [17169] → [627], [17169] archived\n";
    $ok++;
} else {
    echo "NOT FOUND SP for [17169]\n";
}

// 3. Insert SP for ATEM products with no record yet
// Format: product_id => [article, cost_byn, qty]
$inserts = [
    3990 => ['Ж3-КС-Г-007СН',  1115.00,  0],
    8403 => ['Ж10-КС-Г-010СН', 1995.00, 17],
    8413 => ['Ж10-КС-Г-012СН', 2060.00, 29],
    8415 => ['Ж10-КС-Г-015СН', 2160.00,  6],
    // Ж-М АОГВ/АДГВ — "no match" in sync; insert so strategy-1 picks them up on next run
    621  => ['АОГВ-10СН', 0.00, 0],
    633  => ['АДГВ-12СН', 0.00, 0],
];
foreach ($inserts as $productId => [$article, $cost, $qty]) {
    $exists = DB::table('supplier_products')
        ->where('supplier_id', $sid)->where('product_id', $productId)->exists();
    if ($exists) {
        echo "SKIP [$productId] — SP already exists\n";
        continue;
    }
    $sku = DB::table('products')->where('id', $productId)->value('sku');
    DB::table('supplier_products')->insert([
        'supplier_id'          => $sid,
        'product_id'           => $productId,
        'product_sku'          => $sku,
        'supplier_article'     => $article,
        'supplier_name'        => $article,
        'price'                => $cost,
        'currency'             => 'BYN',
        'currency_rate'        => 1.0,
        'price_byn'            => $cost,
        'in_stock'             => $qty > 0,
        'stock_quantity'       => $qty,
        'stock_status'         => $qty > 0 ? 'in_stock' : 'out_of_stock',
        'match_status'         => 'manual',
        'match_confidence'     => 99,
        'last_synced_at'       => $now,
        'created_at'           => $now,
        'updated_at'           => $now,
    ]);
    echo "INSERTED [$productId] $sku → $article\n";
    $ok++;
}

echo "\nDone. Actions: $ok\n";
