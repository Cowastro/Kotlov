<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->where('slug', 'kaminy-ecokamin-rio-obzor-treh-modeley')
            ->update([
                'is_published' => true,
                'published_at' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // The article should remain published.
    }
};
