<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Photos and technical data of the Maitek electric boilers (HOTEX BE/BES, УМТ «Сангай»
 * and «Сангай-МК») from the Onliner catalogue: the makers have no site of their own.
 * Data: resources/data/suppliers/maitek-onliner-media.json, keyed by the Maitek price
 * row (supplier_products.supplier_name); photos in public/img/products/maitek.
 * Only empty photos, specs and short descriptions are filled; the cards hidden until
 * a photo is found are published by catalog:hide-cards-awaiting-photos --publish.
 *
 *   php artisan catalog:apply-maitek-onliner-media            # dry run
 *   php artisan catalog:apply-maitek-onliner-media --apply
 */
class ApplyMaitekOnlinerMediaCommand extends Command
{
    protected $signature = 'catalog:apply-maitek-onliner-media {--apply : Write photos and specs}';

    protected $description = 'Fill Maitek HOTEX / Сангай cards with photos and specs from Onliner';

    public const DATA_FILE = 'data/suppliers/maitek-onliner-media.json';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $items = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true, 512, JSON_THROW_ON_ERROR);
        foreach ($items as $item) {
            foreach ($item['images'] as $image) {
                if (! is_file(public_path(ltrim($image, '/')))) {
                    $this->error('Нет изображения: '.$image);

                    return self::FAILURE;
                }
            }
        }

        $supplierId = DB::table('suppliers')->where('code', 'maitek-group')->value('id');
        $links = DB::table('supplier_products')->where('supplier_id', $supplierId)->whereNotNull('product_id')
            ->get(['supplier_name', 'product_id'])
            ->mapWithKeys(fn ($link) => [self::key((string) $link->supplier_name) => (int) $link->product_id]);

        $stats = ['items' => count($items), 'found' => 0, 'missing' => 0, 'images_set' => 0, 'specs_set' => 0, 'short_set' => 0, 'renamed' => 0];
        foreach ($items as $item) {
            $product = ($id = $links[self::key($item['supplier_name'])] ?? null) ? Product::query()->find($id) : null;
            if (! $product) {
                $stats['missing']++;
                $this->line('audit.maitek_media.missing='.Str::slug($item['supplier_name']));

                continue;
            }
            $stats['found']++;

            $changes = [];
            // A name still as the price sync wrote it («HOTEX HOTEX BE 4 кВт…»): give it a catalogue title.
            if ($product->name === $item['supplier_name']) {
                $changes['name'] = $changes['h1'] = self::title($item['supplier_name']);
                $changes['meta_title'] = $changes['name'].' купить в %city%';
                $stats['renamed']++;
            }
            if (empty($product->images)) {
                $changes['images'] = $item['images'];
                $stats['images_set']++;
            }
            if (empty($product->specs) && $item['specs'] !== []) {
                $changes['specs'] = collect($item['specs'])->map(fn (array $spec) => ['key' => $spec[0], 'value' => $spec[1]])->values()->all();
                $stats['specs_set']++;
            }
            if (blank($product->short_description) && ($short = $this->shortDescription($changes['name'] ?? $product->name, $item['specs'])) !== '') {
                $changes['short_description'] = $short;
                $stats['short_set']++;
            }
            if ($changes !== [] && blank($product->content_source_url)) {
                $changes['content_source_url'] = $item['onliner'];
            }
            $this->line(sprintf('audit.maitek_media.card=%d:%s:%s', $product->id, $product->slug, $changes === [] ? 'filled' : implode('.', array_keys($changes))));

            if ($apply && $changes !== []) {
                $product->forceFill($changes)->save();
            }
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.maitek_media.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /** «HOTEX HOTEX BE 4 кВт c Wi-Fi» and a look-alike spelling give one key. */
    public static function key(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x', ',' => '.']);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /** «HOTEX HOTEX BE 4 кВт c Wi-Fi» → «Электрический котел HOTEX BE 4 кВт c Wi-Fi», «Сангай Котел ЭВПМ-3 …» → «Электрический котел ЭВПМ-3 …». */
    public static function title(string $supplierName): string
    {
        [$brand, $rest] = array_pad(explode(' ', trim($supplierName), 2), 2, '');
        if (! str_contains(mb_strtolower($rest), mb_strtolower($brand))) {
            $rest = $brand.' '.$rest;
        }
        $rest = preg_replace('/^котел\s+/iu', '', trim($rest)) ?? $rest;

        return 'Электрический котел '.$rest;
    }

    /** @param  array<int,array{0:string,1:string}>  $specs */
    private function shortDescription(string $name, array $specs): string
    {
        $spec = collect($specs)->mapWithKeys(fn (array $row) => [$row[0] => $row[1]]);
        $parts = array_filter([
            $spec->get('Расположение') ? Str::lower($spec->get('Расположение')) : null,
            $spec->get('Напряжение питания электрокотла') ? 'питание '.str_replace('V', ' В', $spec->get('Напряжение питания электрокотла')) : null,
            $spec->get('Площадь обогрева') ? 'площадь обогрева до '.str_replace('м2', 'м²', $spec->get('Площадь обогрева')) : null,
            $spec->get('Объем расширительного бака') ? 'встроенный расширительный бак '.$spec->get('Объем расширительного бака') : null,
        ]);

        return $parts === [] ? '' : $name.': '.implode(', ', $parts).'.';
    }
}
