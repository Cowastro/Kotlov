<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reflex DE 12 and DE 25 are on sale again through VISTAR: their archived cards come back (shown when they have a
 * photo) instead of new ones; the VISTAR rows are linked to them by the sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['reflex-de-12', 'reflex-de-25'] as $slug) {
            $card = DB::table('products')->where('slug', $slug)->where('is_archived', true)->first();
            if (! $card) {
                continue;
            }
            $images = json_decode((string) $card->images, true);
            DB::table('products')->where('id', $card->id)->update([
                'is_archived' => false,
                'is_active' => is_array($images) && $images !== [],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The cards stay on sale.
    }
};
