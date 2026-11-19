<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Hand-written content for our own cards, kept in git: database/data/<file>.json maps a card slug to
 * name, content, short_description, meta_title, meta_description, h1, specs ([{key, value}]) and images (paths under public/img/…).
 * Only the given fields change; a missing card or photo is reported and skipped.
 *
 *   php artisan catalog:apply-card-content gamma_san_lyuki_content          # dry run
 *   php artisan catalog:apply-card-content gamma_san_lyuki_content --apply
 */
class ApplyCardContentCommand extends Command
{
    private const FIELDS = ['name', 'content', 'short_description', 'meta_title', 'meta_description', 'h1', 'images', 'specs'];

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
            // «characteristics»: rows of the table to correct by name, in the attribute values and in the specs JSON.
            $fixes = $this->characteristics($product, (array) ($data['characteristics'] ?? []), (bool) $this->option('apply'));
            if ($fixes > 0) {
                $dirty[] = 'characteristics';
            }
            $this->line(sprintf('audit.card_content.card=%d:%s:%s', $product->id, $product->slug, $dirty ? implode('-', $dirty) : 'same'));
            if ($dirty && $this->option('apply')) {
                $product->update(Arr::only($values, array_diff($dirty, ['characteristics'])));
                $changed++;
            }
        }
        $this->line('metric.card_content.updated='.$changed);

        return self::SUCCESS;
    }

    /** Sets the rows of the characteristics table given by name; returns how many differ. */
    private function characteristics(Product $product, array $rows, bool $apply): int
    {
        $normal = fn (string $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
        $values = \App\Models\ProductAttributeValue::query()->with('attribute', 'option')->where('product_id', $product->id)->get()->filter(fn ($value) => $value->attribute !== null);
        $specs = (array) ($product->specs ?? []);
        $differ = 0;
        $specsChanged = false;
        foreach ($rows as $name => $wanted) {
            $wanted = trim((string) $wanted);
            $found = [];
            foreach ($values->filter(fn ($value) => $normal($value->attribute->name) === $normal((string) $name)) as $value) {
                // «154 кг» for an attribute with the suffix «кг» is kept as «154».
                $suffix = trim((string) $value->attribute->suffix);
                $wanted = $suffix !== '' && $value->attribute->type === 'value' && str_ends_with($normal($wanted), $normal($suffix))
                    ? trim(mb_substr($wanted, 0, mb_strlen($wanted) - mb_strlen($suffix))) : $wanted;
                $current = match ($value->attribute->type) { 'select' => (string) $value->option?->name, 'check' => $value->is_checked ? 'Да' : 'Нет', default => (string) $value->value };
                $found[] = 'attribute';
                if ($normal($current) === $normal($wanted)) {
                    continue;
                }
                $differ++;
                if ($apply) {
                    $value->update(match ($value->attribute->type) {
                        'select' => ['option_id' => \App\Models\AttributeOption::query()->firstOrCreate(['attribute_id' => $value->attribute_id, 'name' => $wanted])->id],
                        'check' => ['is_checked' => in_array($normal($wanted), ['да', 'есть', 'yes', '1'], true)],
                        default => ['value' => $wanted],
                    });
                }
            }
            foreach ($specs as $index => $row) {
                if (is_array($row) && $normal((string) ($row['key'] ?? '')) === $normal((string) $name)) {
                    $found[] = 'specs';
                    if ($normal((string) ($row['value'] ?? '')) !== $normal($wanted)) {
                        $differ++;
                        $specs[$index]['value'] = $wanted;
                        $specsChanged = true;
                    }
                }
            }
            $this->line(sprintf('audit.card_content.characteristic=%d:%s:%s', $product->id, Str::slug((string) $name), $found ? implode('-', array_unique($found)) : 'missing'));
        }
        if ($apply && $specsChanged) {
            $product->update(['specs' => $specs]);
        }

        return $differ;
    }
}
