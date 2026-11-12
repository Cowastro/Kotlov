<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Hand-written content for our own cards, kept in git: database/data/<file>.json maps a card slug to
 * content, short_description, meta_title, meta_description, h1 and images (paths under public/img/…).
 * Only the given fields change; a missing card or photo is reported and skipped.
 *
 *   php artisan catalog:apply-card-content gamma_san_lyuki_content          # dry run
 *   php artisan catalog:apply-card-content gamma_san_lyuki_content --apply
 */
class ApplyCardContentCommand extends Command
{
    private const FIELDS = ['content', 'short_description', 'meta_title', 'meta_description', 'h1', 'images'];

    protected $signature = 'catalog:apply-card-content
        {file : JSON file name in database/data (without .json)}
        {--apply : Write the content}';

    protected $description = 'Apply hand-written content and photos from database/data/<file>.json to cards by slug';

    public function handle(): int
    {
        $name = Str::slug((string) $this->argument('file'), '_');
        $path = database_path('data/'.$name.'.json');
        $rows = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($rows)) {
            $this->line('audit.card_content.skipped=file:missing');

            return self::FAILURE;
        }

        $changed = 0;
        foreach ($rows as $slug => $data) {
            $product = Product::query()->where('slug', $slug)->first();
            $images = array_map(fn ($image) => ltrim((string) $image, '/'), (array) ($data['images'] ?? []));
            $reason = match (true) {
                ! $product => 'no_card',
                collect($images)->contains(fn ($image) => ! str_starts_with($image, 'img/') || ! is_file(public_path($image))) => 'no_image',
                default => null,
            };
            if ($reason !== null) {
                $this->line('audit.card_content.skipped='.Str::slug((string) $slug).':'.$reason);

                continue;
            }

            $values = Arr::only($data, self::FIELDS);
            if (array_key_exists('images', $data)) {
                $values['images'] = $images;
            }
            $dirty = collect($values)->filter(fn ($value, $field) => $product->{$field} != $value)->keys()->all();
            $this->line(sprintf('audit.card_content.card=%d:%s:%s', $product->id, $product->slug, $dirty ? implode('-', $dirty) : 'same'));
            if ($dirty && $this->option('apply')) {
                $product->update(Arr::only($values, $dirty));
                $changed++;
            }
        }
        $this->line('metric.card_content.updated='.$changed);

        return self::SUCCESS;
    }
}
