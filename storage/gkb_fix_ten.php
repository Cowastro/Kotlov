<?php
// Fix duplicate ТЭН: link new SP to pid=17181, archive the duplicate product.
// Run: /opt/alt/php83/usr/bin/php artisan tinker --execute="require storage_path('gkb_fix_ten.php');"

$sid = DB::table('suppliers')->where('code', 'gazkotelbel')->value('id');
echo "Supplier id: $sid\n";

$newSp = DB::table('supplier_products')
    ->where('supplier_id', $sid)
    ->where('supplier_article', 'ТЭН')
    ->first();

if (!$newSp) {
    echo "SP with article=ТЭН not found — maybe already fixed.\n";
    return;
}

$newPid = $newSp->product_id;
echo "New duplicate ТЭН pid: $newPid\n";

if ($newPid == 17181) {
    echo "product_id is already 17181 — no action needed.\n";
    return;
}

// 1. Delete new SP (points to duplicate product)
DB::table('supplier_products')->where('id', $newSp->id)->delete();
echo "Deleted new SP id={$newSp->id}\n";

// 2. Delete old SP for 17181 with wrong article ТЭН-ЖИТОМИР3
$deleted = DB::table('supplier_products')
    ->where('supplier_id', $sid)
    ->where('product_id', 17181)
    ->where('supplier_article', 'ТЭН-ЖИТОМИР3')
    ->delete();
echo "Deleted old wrong SP (ТЭН-ЖИТОМИР3): $deleted rows\n";

// 3. Insert correct SP (23, 17181, 'ТЭН') with price/stock from the new one
DB::table('supplier_products')->insert([
    'supplier_id'      => $sid,
    'product_id'       => 17181,
    'supplier_article' => 'ТЭН',
    'supplier_name'    => $newSp->supplier_name,
    'price'            => $newSp->price,
    'price_byn'        => $newSp->price_byn,
    'currency'         => $newSp->currency,
    'currency_rate'    => 1,
    'in_stock'         => $newSp->in_stock,
    'stock_quantity'   => $newSp->stock_quantity,
    'stock_status'     => $newSp->stock_status,
    'match_status'     => 'manual',
    'match_confidence' => 99,
    'created_at'       => now(),
    'updated_at'       => now(),
]);
echo "Inserted correct SP (17181, ТЭН)\n";

// 4. Archive duplicate product
DB::table('products')->where('id', $newPid)->update([
    'is_archived' => true,
    'updated_at'  => now(),
]);
echo "Archived pid=$newPid\n";
echo "Done.\n";
