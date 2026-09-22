<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DUPLICATES = [
        'nakopitelnyiy-vodonagrevatel-thermex-ic-10-u' => 'thermex-ic-10-u',
        'nakopitelnyiy-vodonagrevatel-thermex-ic-15-o' => 'thermex-ic-15-o',
        'nakopitelnyiy-vodonagrevatel-thermex-ic-15-u' => 'thermex-ic-15-u',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DUPLICATES as $duplicateSlug => $canonicalSlug) {
            $duplicate = DB::table('products')->where('slug', $duplicateSlug)->first();
            $canonical = DB::table('products')->where('slug', $canonicalSlug)->first();

            if (! $duplicate || ! $canonical) {
                continue;
            }

            $canonicalIsUsable = (bool) $canonical->is_active && ! (bool) $canonical->is_archived;
            if ($canonicalIsUsable) {
                continue;
            }

            DB::table('products')->where('id', $duplicate->id)->update([
                'is_active' => true,
                'is_archived' => false,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('redirects')) {
                continue;
            }

            $categorySlug = DB::table('categories')->where('id', $duplicate->category_id)->value('slug');
            if ($categorySlug) {
                DB::table('redirects')
                    ->where('from_url', '/' . $categorySlug . '/' . $duplicate->slug)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Intentionally left blank: this migration restores public fallbacks
        // only where the intended replacement card is not usable.
    }
};
