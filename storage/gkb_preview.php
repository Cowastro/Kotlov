<?php

$sid = DB::table('suppliers')->where('code', 'gazkotelbel')->value('id');

$mapping = [
    3990 => 'Ж3-КС-Г-007СН',
    623  => 'Ж3-КС-Г-010СН',
    624  => 'Ж3-КС-Г-012СН',
    628  => 'Ж3-КС-Г-015СН',
    632  => 'Ж3-КС-Г-020СН',
    2524 => 'Ж3-КС-Г-025СН',
    635  => 'Ж3-КС-Г-030СН',
    637  => 'Ж3-КС-Г-045СН',
    625  => 'Ж3-КС-ГВ-010СН',
    627  => 'Ж3-КС-ГВ-012СН',
    631  => 'Ж3-КС-ГВ-015СН',
    634  => 'Ж3-КС-ГВ-020СН',
    2525 => 'Ж3-КС-ГВ-025СН',
    636  => 'Ж3-КС-ГВ-030СН',
    8403 => 'Ж10-КС-Г-010СН',
    8413 => 'Ж10-КС-Г-012СН',
    8415 => 'Ж10-КС-Г-015СН',
];

echo str_pad('АТЕМ id/sku', 28) . str_pad('GKB article', 22) . str_pad('КОТLOV id/sku', 26) . "photo  copy\n";
echo str_repeat('-', 90) . "\n";

foreach ($mapping as $atemId => $article) {
    $sp = DB::table('supplier_products')
        ->where('supplier_id', $sid)
        ->where('supplier_article', $article)
        ->first();

    if (!$sp) {
        echo str_pad($article, 22) . " — NO supplier_products!\n";
        continue;
    }

    $atem   = DB::table('products')->where('id', $atemId)->first(['id', 'sku', 'images', 'content', 'specs']);
    $kotlov = DB::table('products')->where('id', $sp->product_id)->first(['id', 'sku', 'content', 'specs']);

    $willCopy = [];
    if (empty($atem->content) && !empty($kotlov->content)) {
        $willCopy[] = 'content';
    }
    if ((empty($atem->specs) || $atem->specs === '[]') && !empty($kotlov->specs)) {
        $willCopy[] = 'specs';
    }

    $photo = (!empty($atem->images) && $atem->images !== '[]') ? 'Y' : 'N';

    echo str_pad('[' . $atemId . '] ' . $atem->sku, 28)
        . str_pad($article, 22)
        . str_pad('[' . $sp->product_id . '] ' . $kotlov->sku, 26)
        . '  ' . $photo . '     ' . implode(',', $willCopy) . "\n";
}

echo "\n=== БЕЗ КОТLOV-ДУБЛЯ (нужно создать SP) ===\n";
foreach ([621 => 'АОГВ-10СН', 633 => 'АДГВ-12СН'] as $id => $art) {
    $exists = DB::table('supplier_products')
        ->where('supplier_id', $sid)
        ->where('supplier_article', $art)
        ->exists();
    $p = DB::table('products')->where('id', $id)->first(['id', 'sku', 'name']);
    echo '[' . $id . '] ' . $p->sku . ' | ' . $art . ' | SP=' . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
