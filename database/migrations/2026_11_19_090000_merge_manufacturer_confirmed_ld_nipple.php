<?php

use App\Services\MergeConfirmedBeregProducts;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MergeConfirmedBeregProducts::class)->run(json_decode(file_get_contents(resource_path('data/ld-reviewed-duplicates-2026-11-19.json')), true, 512, JSON_THROW_ON_ERROR));
    }

    public function down(): void
    {
        // The private product-merges backup preserves the pre-change records.
    }
};
