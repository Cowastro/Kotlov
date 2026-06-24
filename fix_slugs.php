<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;

$bad = DB::table('products')
    ->where(function($q) {
        $q->where('slug', 'REGEXP', '\\[')
          ->orWhere('slug', 'REGEXP', '\\]')
          ->orWhere('slug', 'REGEXP', '\\(')
          ->orWhere('slug', 'REGEXP', '\\)')
          ->orWhere('slug', 'LIKE', '%,%')
          ->orWhere('slug', 'LIKE', '% %');
    })
    ->select('id', 'slug', 'name')
    ->orderBy('id')
    ->get();

echo 'Total to fix: ' . count($bad) . PHP_EOL;

if (count($bad) === 0) {
    echo 'Nothing to do.' . PHP_EOL;
    exit;
}

$fixed = 0;
$conflicts = 0;

foreach ($bad as $p) {
    $newSlug = Str::slug($p->slug);

    if ($newSlug === $p->slug) {
        continue;
    }

    $exists = DB::table('products')
        ->where('slug', $newSlug)
        ->where('id', '!=', $p->id)
        ->exists();

    if ($exists) {
        $newSlug = $newSlug . '-' . $p->id;
        $conflicts++;
        echo "CONFLICT id={$p->id}: {$p->slug} => {$newSlug}" . PHP_EOL;
    }

    DB::table('products')->where('id', $p->id)->update(['slug' => $newSlug]);
    $fixed++;
    echo "Fixed #{$p->id}: [{$p->slug}] => [{$newSlug}]" . PHP_EOL;
}

echo PHP_EOL . "Done. Fixed: {$fixed}, Conflicts: {$conflicts}" . PHP_EOL;
