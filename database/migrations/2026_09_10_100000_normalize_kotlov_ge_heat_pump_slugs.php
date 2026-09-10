<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY = 'teplovyie-nasosyi';

    private const SLUGS = [
        'teplovoy-nasos-vozduh-voda-b3sd' => 'kotlov-ge-flm30-r32-10-kvt',
        'teplovoy-nasos-vozduh-voda-b5sd' => 'kotlov-ge-flm40-r32-16-kvt',
        'teplovoy-nasos-centrometal-12-kvt' => 'kotlov-ge-nl-flm50-190ii-r290-19-kvt',
        'teplovoy-nasos-centrometal-12-kvt-380v' => 'kotlov-ge-nl-flm50-160ii-r290-16-kvt',
        'teplovoy-nasos-centrometal-14-kvt' => 'kotlov-ge-nl-flm30-130ii-r290-12-8-kvt',
        'teplovoy-nasos-centrometal-16-kvt' => 'kotlov-ge-nl-flm30-100ii-r290-10-kvt',
        'hotta-teplovoy' => 'kotlov-ge-olympus-r32-12-5-kvt',
        'teplovoy-nasos-hotta-flm80-r32-30-kvt' => 'kotlov-ge-flm80-r32-30-kvt',
        'teplovoy-nasos-hotta-flm60-r32-23-kvt' => 'kotlov-ge-flm60-r32-23-kvt',
    ];

    public function up(): void
    {
        $categoryId = DB::table('categories')->where('slug', self::CATEGORY)->value('id');
        if (! $categoryId) {
            return;
        }

        foreach (self::SLUGS as $oldSlug => $newSlug) {
            $product = DB::table('products')
                ->where('category_id', $categoryId)
                ->where('slug', $oldSlug)
                ->first(['id']);

            if (! $product || DB::table('products')->where('slug', $newSlug)->exists()) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'slug' => $newSlug,
                'updated_at' => now(),
            ]);

            $from = '/' . self::CATEGORY . '/' . $oldSlug;
            $to = '/' . self::CATEGORY . '/' . $newSlug;

            DB::table('redirects')->updateOrInsert(
                ['from_url' => $from],
                [
                    'to_url' => $to,
                    'status_code' => 301,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('blog_posts')->where('content', 'like', '%' . $from . '%')->get(['id', 'content'])
                ->each(function ($post) use ($from, $to) {
                    DB::table('blog_posts')->where('id', $post->id)->update([
                        'content' => str_replace($from, $to, $post->content),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')->where('slug', self::CATEGORY)->value('id');
        if (! $categoryId) {
            return;
        }

        foreach (self::SLUGS as $oldSlug => $newSlug) {
            DB::table('products')
                ->where('category_id', $categoryId)
                ->where('slug', $newSlug)
                ->update(['slug' => $oldSlug, 'updated_at' => now()]);

            $from = '/' . self::CATEGORY . '/' . $oldSlug;
            $to = '/' . self::CATEGORY . '/' . $newSlug;

            DB::table('blog_posts')->where('content', 'like', '%' . $to . '%')->get(['id', 'content'])
                ->each(function ($post) use ($from, $to) {
                    DB::table('blog_posts')->where('id', $post->id)->update([
                        'content' => str_replace($to, $from, $post->content),
                        'updated_at' => now(),
                    ]);
                });

            DB::table('redirects')->where('from_url', $from)->where('to_url', $to)->delete();
        }
    }
};
