<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUGS = [
        'pelletnyi-kotel-sakovich-pellet-max-150-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-max-50-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-max-75-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-max-99-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-10-s-gorelkoi-praktik-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-15-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-15-s-gorelkoi-praktik-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-20-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-20-s-gorelkoi-praktik-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-25-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-25-s-gorelkoi-praktik-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-standart-35-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-universal-16-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-universal-20-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-universal-26-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-universal-36-s-gorelkoi-kipi-rotary-i-bunkerom',
        'pelletnyi-kotel-sakovich-pellet-universal-50-s-gorelkoi-kipi-rotary-i-bunkerom',
    ];

    public function up(): void
    {
        $this->move('tverdotoplivnye', 'kotly-na-pelletah');
    }

    public function down(): void
    {
        $this->move('kotly-na-pelletah', 'tverdotoplivnye');
    }

    private function move(string $fromSlug, string $toSlug): void
    {
        $fromCategoryId = DB::table('categories')->where('slug', $fromSlug)->value('id');
        $toCategoryId = DB::table('categories')->where('slug', $toSlug)->value('id');

        if (! $fromCategoryId || ! $toCategoryId) {
            return;
        }

        DB::table('products')
            ->where('category_id', $fromCategoryId)
            ->whereIn('slug', self::PRODUCT_SLUGS)
            ->update([
                'category_id' => $toCategoryId,
                'updated_at' => now(),
            ]);
    }
};
