<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

/**
 * Hand-picked photos for cards that no automatic source covers: copy the photos of another card
 * (the same product family) or download a photo from a given page image. Only cards without
 * photos are touched.
 *
 *   php artisan catalog:attach-photos --copy=30207:11152 --url=30194:https://…/image.jpeg
 *   … --apply
 */
class AttachProductPhotosCommand extends Command
{
    protected $signature = 'catalog:attach-photos
        {--copy=* : TARGET_ID:SOURCE_ID — copy the photos of the source card}
        {--url=* : TARGET_ID:IMAGE_URL — download this image}
        {--apply : Write the photos}';

    protected $description = 'Give cards without photos the photos of another card or a given image';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $stats = ['copied' => 0, 'downloaded' => 0, 'skipped' => 0];
        foreach ((array) $this->option('copy') as $pair) {
            [$targetId, $sourceId] = array_map('intval', array_pad(explode(':', (string) $pair, 2), 2, 0));
            $target = Product::query()->find($targetId);
            $source = Product::query()->find($sourceId);
            if (! $target || ! $source || ! empty($target->images) || empty($source->images)) {
                $stats['skipped']++;
                $this->line("audit.attach_photos.skipped={$targetId}");

                continue;
            }
            $this->line("audit.attach_photos.copy={$targetId}:{$sourceId}:".count((array) $source->images));
            if ($this->option('apply')) {
                $target->forceFill(['images' => array_values((array) $source->images)])->save();
            }
            $stats['copied']++;
        }
        foreach ((array) $this->option('url') as $pair) {
            [$targetId, $url] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $target = Product::query()->find((int) $targetId);
            if (! $target || ! empty($target->images) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                $stats['skipped']++;
                $this->line('audit.attach_photos.skipped='.(int) $targetId);

                continue;
            }
            $saved = 0;
            if ($this->option('apply')) {
                $saved = (int) ($enricher->enrichFromParsed($target, $url, ['images' => []], [
                    'verified_images' => [$url], 'update_images' => true, 'update_specs' => false, 'update_service' => false,
                    'update_documents' => false, 'update_video' => false, 'update_content' => false,
                ])['images_saved'] ?? 0);
            }
            $this->line('audit.attach_photos.url='.(int) $targetId.':'.$saved);
            $stats['downloaded'] += $saved;
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.attach_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
