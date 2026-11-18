<?php

use App\Models\BlogPost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $post = BlogPost::where('slug', 'dymohod-teplov-suhov-pelletnyy-kotel-lida')->first();
        if (! $post) {
            return;
        }
        $map = [];
        foreach (['cover', 'pellet-boiler', 'boiler-connection', 'chimney-exterior', 'chimney-overview'] as $name) {
            $base = 'img/blog/works/lida-pellet-chimney/'.$name;
            $map[$base.'.webp'] = $base.'-v3.webp';
        }
        $content = strtr($post->content, $map);
        $content = str_replace('Фотографии предоставлены для этого проекта и обработаны для публикации.', 'Фотографии обработаны для публикации: убраны посторонние предметы и отретушированы дефекты отделки стен.', $content);
        $changes = ['content' => $content, 'cover_image' => $map[$post->cover_image] ?? $post->cover_image,
            'images' => array_map(fn ($image) => $map[$image] ?? $image, $post->images ?? [])];
        if ($post->only(array_keys($changes)) === $changes) {
            return;
        }
        $path = 'private/blog-backups/lida-chimney-walls-'.$post->id.'.json';
        if (! Storage::disk('local')->exists($path) && ! Storage::disk('local')->put($path, json_encode($post->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Не удалось сохранить резервную копию статьи.');
        }
        $post->update($changes);
    }

    public function down(): void
    {
        // Keep the published photo revision; the prior article and assets remain available.
    }
};
