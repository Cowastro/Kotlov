<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->string('storefront_title')->nullable()->after('map_url');
            $table->text('storefront_intro')->nullable()->after('storefront_title');
            $table->text('storefront_promo')->nullable()->after('storefront_intro');
            $table->string('hero_image')->nullable()->after('storefront_promo');
            $table->json('gallery')->nullable()->after('hero_image');
            $table->string('map_embed_url', 1000)->nullable()->after('gallery');
            $table->string('seo_title')->nullable()->after('map_embed_url');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->dropColumn([
                'storefront_title', 'storefront_intro', 'storefront_promo',
                'hero_image', 'gallery', 'map_embed_url', 'seo_title', 'seo_description',
            ]);
        });
    }
};
