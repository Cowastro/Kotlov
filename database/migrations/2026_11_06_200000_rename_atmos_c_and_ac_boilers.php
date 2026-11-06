<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The ATMOS price list types the C and AC models in Cyrillic («С 15 S»), so their cards were named «С ATMOS 15 S».
 * They become «Твердотопливный котел ATMOS C 15 S» (and «… с пеллетной горелкой» for the boiler + burner sets),
 * the old addresses redirect to the new ones.
 */
return new class extends Migration
{
    /** old slug => [model, a boiler + pellet burner set] */
    private const CARDS = [
        's-atmos-15-s' => ['C 15 S', true],
        's-atmos-18-s' => ['C 18 S', true],
        's-atmos-25-st' => ['C 25 ST', true],
        's-atmos-32-st' => ['C 32 ST', true],
        'as-atmos-25-s' => ['AC 25 S', true],
        's-atmos-15-s-2' => ['C 15 S', false],
        's-atmos-18-s-2' => ['C 18 S', false],
        's-atmos-25-st-2' => ['C 25 ST', false],
        's-atmos-40-s' => ['C 40 S', false],
        's-atmos-50-s' => ['C 50 S', false],
        'as-atmos-16-s' => ['AC 16 S', false],
        'as-atmos-25-s-2' => ['AC 25 S', false],
    ];

    public function up(): void
    {
        foreach (self::CARDS as $oldSlug => [$model, $set]) {
            $card = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.slug', $oldSlug)->where('p.sku', 'like', 'ATMOS-%')
                ->first(['p.id', 'p.name', 'p.meta_title', 'p.meta_description', 'p.short_description', 'p.content', 'c.slug as category']);
            if (! $card) {
                continue;
            }
            $name = 'Твердотопливный котел ATMOS '.$model.($set ? ' с пеллетной горелкой' : '');
            $slug = \Illuminate\Support\Str::slug(str_replace(' ', '-', 'tverdotoplivnyi-kotel-atmos-'.$model.($set ? '-s-pelletnoi-gorelkoi' : '')));
            if (DB::table('products')->where('slug', $slug)->where('id', '!=', $card->id)->exists()) {
                $slug = $oldSlug;
            }
            $swap = fn (?string $text): ?string => $text === null ? null : str_replace($card->name, $name, $text);
            DB::table('products')->where('id', $card->id)->update([
                'name' => $name,
                'h1' => $name,
                'slug' => $slug,
                'meta_title' => $swap($card->meta_title),
                'meta_description' => $swap($card->meta_description),
                'short_description' => $swap($card->short_description),
                'content' => $swap($card->content),
                'updated_at' => now(),
            ]);
            if ($slug !== $oldSlug && $card->category) {
                DB::table('redirects')->updateOrInsert(
                    ['from_url' => '/'.$card->category.'/'.$oldSlug],
                    ['to_url' => '/'.$card->category.'/'.$slug, 'status_code' => 301, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        // Not renamed back.
    }
};
