<?php
// Fix supplier_articles overwritten by fuzzy-match after repair.php.
// Run: /opt/alt/php83/usr/bin/php artisan tinker --execute="require '/var/www/h209767/data/www/new.kotlov.by/storage/gkb_repair2.php';"

$sid = DB::table('suppliers')->where('code', 'gazkotelbel')->value('id');
$now = now();

// product_id => correct_article
$fixes = [
    628   => 'Ж3-КС-Г-015СН',
    8403  => 'Ж10-КС-Г-010СН',
    8413  => 'Ж10-КС-Г-012СН',
    17183 => 'АОТВ-12',
];

foreach ($fixes as $productId => $article) {
    $rows = DB::table('supplier_products')
        ->where('supplier_id', $sid)
        ->where('product_id', $productId)
        ->update(['supplier_article' => $article, 'updated_at' => $now]);
    $name = DB::table('products')->where('id', $productId)->value('name');
    echo ($rows ? 'FIXED' : 'NOT FOUND') . " [$productId] → $article | $name\n";
}

echo "\nDone.\n";
