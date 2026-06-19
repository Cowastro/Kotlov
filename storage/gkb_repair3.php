<?php
// Fix 3 remaining wrong supplier_articles.
// Run: /opt/alt/php83/usr/bin/php artisan tinker --execute="require '/var/www/h209767/data/www/new.kotlov.by/storage/gkb_repair3.php';"

$sid = DB::table('suppliers')->where('code', 'gazkotelbel')->value('id');
$now = now();

$fixes = [
    // product_id => correct article
    17176 => 'ТУРБО-КС-Г-16СН',  // Турбо 16кВт was mapped to 30СН article
    17182 => 'ЖИТОМИР-14М',       // 14М product was mapped to 22М article
    17181 => 'ТЭН-ЖИТОМИР3',     // ТЭН accessory got ЖИТОМИР-3 (extractArticle bug — now fixed)
];

foreach ($fixes as $productId => $article) {
    $rows = DB::table('supplier_products')
        ->where('supplier_id', $sid)->where('product_id', $productId)
        ->update(['supplier_article' => $article, 'updated_at' => $now]);
    $name = DB::table('products')->where('id', $productId)->value('name');
    echo ($rows ? 'FIXED' : 'NOT FOUND') . " [$productId] → $article | $name\n";
}

echo "\nDone.\n";
