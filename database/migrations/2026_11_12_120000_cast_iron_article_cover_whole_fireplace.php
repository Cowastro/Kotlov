<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The cast iron lining article gets a cover with the whole ДЕЛЬТА ПРАЙМ 1100 (the first one showed a cut-out of it). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')->where('slug', 'chugunnaya-futerovka-kamina-preimushchestva')->update([
            'cover_image' => 'img/blog/chugunnaya-futerovka/chugunnaya-futerovka-cover-v2.webp',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', 'chugunnaya-futerovka-kamina-preimushchestva')
            ->update(['cover_image' => 'img/blog/chugunnaya-futerovka/chugunnaya-futerovka-cover.webp']);
    }
};
