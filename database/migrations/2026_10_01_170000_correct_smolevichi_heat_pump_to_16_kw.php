<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_SLUG = 'montazh-teplovogo-nasosa-kotlov-ge-10-kvt-r290-smolevichskiy-rayon';
    private const NEW_SLUG = 'montazh-teplovogo-nasosa-kotlov-ge-16-kvt-r290-smolevichskiy-rayon';
    private const OLD_PRODUCT = '/teplovyie-nasosyi/kotlov-ge-nl-flm30-100ii-r290-10-kvt';
    private const NEW_PRODUCT = '/teplovyie-nasosyi/kotlov-ge-nl-flm50-160ii-r290-16-kvt';

    public function up(): void
    {
        $post = DB::table('blog_posts')
            ->whereIn('slug', [self::OLD_SLUG, self::NEW_SLUG])
            ->first();

        if (! $post) {
            return;
        }

        $content = str_replace(
            [
                self::OLD_PRODUCT,
                'NL-FLM30-100II/R290',
                'однофазное питание 220 В.',
                '10 кВт',
            ],
            [
                self::NEW_PRODUCT,
                'NL-FLM50-160II/R290',
                'потребляемая мощность — 3,75 кВт.',
                '16 кВт',
            ],
            $post->content
        );

        $tags = collect(json_decode($post->tags ?: '[]', true))
            ->map(fn ($tag) => $tag === '10 кВт' ? '16 кВт' : $tag)
            ->values()
            ->all();

        DB::table('blog_posts')->where('id', $post->id)->update([
            'slug' => self::NEW_SLUG,
            'title' => 'Монтаж теплового насоса KOTLOV GE 16 кВт R290 в Смолевичском районе',
            'excerpt' => 'Реальный объект KOTLOV в Смолевичском районе: воздушный тепловой насос KOTLOV GE 16 кВт на R290, тёплый пол, горячее водоснабжение, гидравлическая обвязка и пусконаладка.',
            'content' => $content,
            'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'meta_title' => 'Монтаж теплового насоса 16 кВт в Смолевичах | KOTLOV',
            'meta_description' => 'Кейс KOTLOV: монтаж теплового насоса воздух-вода KOTLOV GE 16 кВт R290 в Смолевичском районе. Тёплый пол, ГВС, обвязка и настройка системы.',
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('redirects')) {
            DB::table('redirects')->updateOrInsert(
                ['from_url' => '/blog/' . self::OLD_SLUG],
                [
                    'to_url' => '/blog/' . self::NEW_SLUG,
                    'status_code' => 301,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $post = DB::table('blog_posts')->where('slug', self::NEW_SLUG)->first();

        if ($post) {
            $content = str_replace(
                [
                    self::NEW_PRODUCT,
                    'NL-FLM50-160II/R290',
                    'потребляемая мощность — 3,75 кВт.',
                    '16 кВт',
                ],
                [
                    self::OLD_PRODUCT,
                    'NL-FLM30-100II/R290',
                    'однофазное питание 220 В.',
                    '10 кВт',
                ],
                $post->content
            );

            $tags = collect(json_decode($post->tags ?: '[]', true))
                ->map(fn ($tag) => $tag === '16 кВт' ? '10 кВт' : $tag)
                ->values()
                ->all();

            DB::table('blog_posts')->where('id', $post->id)->update([
                'slug' => self::OLD_SLUG,
                'title' => 'Монтаж теплового насоса KOTLOV GE 10 кВт R290 в Смолевичском районе',
                'excerpt' => 'Реальный объект KOTLOV в Смолевичском районе: воздушный тепловой насос KOTLOV GE 10 кВт на R290, тёплый пол, горячее водоснабжение, гидравлическая обвязка и пусконаладка.',
                'content' => $content,
                'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'meta_title' => 'Монтаж теплового насоса 10 кВт в Смолевичах | KOTLOV',
                'meta_description' => 'Кейс KOTLOV: монтаж теплового насоса воздух-вода KOTLOV GE 10 кВт R290 в Смолевичском районе. Тёплый пол, ГВС, обвязка и настройка системы.',
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('redirects')) {
            DB::table('redirects')
                ->where('from_url', '/blog/' . self::OLD_SLUG)
                ->where('to_url', '/blog/' . self::NEW_SLUG)
                ->delete();
        }
    }
};
