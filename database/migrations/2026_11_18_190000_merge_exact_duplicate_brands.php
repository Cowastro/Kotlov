<?php

use App\Services\MergeDuplicateBrands;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brand_aliases')) {
            Schema::create('brand_aliases', function (Blueprint $table): void {
                $table->unsignedBigInteger('original_id')->primary();
                $table->foreignId('canonical_id')->constrained('brands')->cascadeOnDelete();
                $table->string('slug')->unique();
            });
        }
        app(MergeDuplicateBrands::class)->run();
        if (! Schema::hasIndex('brands', 'brands_name_unique')) {
            Schema::table('brands', fn (Blueprint $table) => $table->unique('name', 'brands_name_unique'));
        }
    }

    public function down(): void
    {
        // Preserve merged data and URL aliases; recovery uses private backups.
    }
};
