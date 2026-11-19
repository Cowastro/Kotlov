<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationProductCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One 1C row with no card and no source page becomes a card from what we know ourselves: our own photo
 * (public/img/…), the maker, specs from its documents. Price 0 means «Цену уточняйте».
 *
 *   php artisan integration:create-onec-card УТ-00009534 lyuki-i-dozhdepriemniki --title="Люк …" --brand="Гамма-Сан"
 *       --image=img/products/gamma-san/lyuk.webp --spec="Нагрузка: 1,5 т" --short="…"          # dry run
 *   … --apply
 */
class CreateOneCCardCommand extends Command
{
    protected $signature = 'integration:create-onec-card
        {code : 1C code of the row}
        {category : Category slug}
        {--title= : Card name (default: the 1C name)}
        {--price=0 : Retail price, BYN; 0 = «Цену уточняйте»}
        {--brand= : Brand name, created inactive if missing}
        {--image=* : Photo path under public (img/…)}
        {--spec=* : «Название: значение»}
        {--short= : Short description}
        {--content= : HTML description}
        {--source=onec : Integration source code}
        {--replace=* : With --like: «было::стало» in the copied texts (220 В::380 В, 3 фазы); «=>» also works locally}
        {--like= : Id of a sibling card (same series): its category, brand, photos, specs and texts are the base}
        {--without-row : Our own card with no 1C row (a model not stocked yet); code is then only a label}
        {--no-images : With --like: do not copy the photos of the sibling (another look: cladding, door, colour)}
        {--hidden : Hide the card from buyers until it has its own photo (catalog:hide-cards-awaiting-photos --publish shows it)}
        {--apply : Create the card and link the row}';

    protected $description = 'Create a card for one 1C row from our own photo and data';

    public function handle(IntegrationProductCreator $creator): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $code = (string) $this->argument('code');
        $standalone = (bool) $this->option('without-row');
        $item = $standalone ? null : IntegrationProduct::query()->where('integration_source_id', $source?->id)
            ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
        $like = $this->option('like') ? \App\Models\Product::query()->find((int) $this->option('like')) : null;
        $categorySlug = (string) $this->argument('category');
        $category = $categorySlug === '-' && $like ? $like->category : Category::query()->where('slug', $categorySlug)->first();
        $images = collect((array) $this->option('image'))->map(fn ($path) => ltrim((string) $path, '/'))->values();
        $reason = match (true) {
            ! $standalone && ! $item => 'no_row',
            (bool) $item?->product_id => 'linked',
            $item?->match_status === 'ignored' => 'ignored',
            $standalone && ! trim((string) $this->option('title')) => 'no_title',
            $this->option('like') && ! $like => 'no_like',
            ! $category => 'no_category',
            $images->contains(fn ($path) => ! str_starts_with($path, 'img/') || ! is_file(public_path($path))) => 'no_image',
            default => null,
        };
        if ($reason !== null) {
            $this->line('audit.onec_card.skipped='.Str::slug($code).':'.$reason);

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('title') ?: $item?->name));
        $own = collect((array) $this->option('spec'))
            ->map(fn ($line) => array_map('trim', explode(':', (string) $line, 2)))
            ->filter(fn ($pair) => count($pair) === 2 && $pair[0] !== '' && $pair[1] !== '')
            ->mapWithKeys(fn ($pair) => [$pair[0] => $pair[1]]);
        // The characteristics table of the sibling is mostly its attribute values: they are copied, a --spec of the same name changes one.
        $attributeValues = $like ? \App\Models\ProductAttributeValue::query()->with('attribute', 'option')->where('product_id', $like->id)->get()
            ->filter(fn ($value) => $value->attribute !== null)->map(function ($value) use ($own): array {
                $name = trim((string) $value->attribute->name);
                $key = $own->keys()->first(fn ($spec) => mb_strtolower(trim((string) $spec)) === mb_strtolower($name));
                $row = ['attribute' => $value->attribute, 'option_id' => $value->option_id, 'value' => $value->value, 'is_checked' => $value->is_checked];
                if ($key !== null) {
                    $row['override'] = $own->pull($key);
                }

                return $row;
            })->values() : collect();
        // A sibling's specs stay in their order; a --spec with the same name replaces the value, new ones go last.
        $specs = collect($like?->specs ?? [])->filter(fn ($row) => is_array($row) && filled($row['key'] ?? null))
            ->map(fn ($row) => ['key' => $row['key'], 'value' => $own->pull($row['key']) ?? ($row['value'] ?? '')])
            ->concat($own->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values())
            ->values()->all();
        if ($images->isEmpty() && $like && ! $this->option('no-images')) {
            $images = collect($like->imagePaths());
        }
        $this->line(sprintf('audit.onec_card.plan=%d:%s:%s:images%d:specs%d', (int) $item?->id, Str::slug($name), $category->slug, $images->count(), count($specs) + $attributeValues->count()));
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $brandId = $like?->brand_id;
        if ($brandName = trim((string) $this->option('brand'))) {
            $brand = Brand::query()->where('name', $brandName)->first()
                ?? Brand::query()->create(['name' => $brandName, 'slug' => Str::slug($brandName), 'is_active' => false]);
            $brandId = $brand->id;
        }
        $data = array_filter([
            'name' => $name,
            'category_id' => $category->id,
            'retail_price' => round((float) str_replace(',', '.', (string) $this->option('price')), 2),
            'brand_id' => $brandId,
            'images' => $images->all() ?: null,
            'specs' => $specs ?: null,
            'short_description' => $this->option('short') ?: ($this->replaced($like?->short_description) ?: null),
            'content' => $this->option('content') ?: ($this->replaced($like?->content) ?: null),
        ], fn ($value) => $value !== null);
        $product = $standalone ? $creator->createStandalone($data) : $creator->createAndLink($item, $data);
        if (! $product) {
            $this->line('audit.onec_card.failed='.$item->id);

            return self::FAILURE;
        }
        foreach ($attributeValues as $row) {
            $attribute = $row['attribute'];
            $values = ['option_id' => $row['option_id'], 'value' => $row['value'], 'is_checked' => $row['is_checked']];
            if (array_key_exists('override', $row)) {
                $values = match ($attribute->type) {
                    'select' => ['option_id' => \App\Models\AttributeOption::query()->firstOrCreate(['attribute_id' => $attribute->id, 'name' => $row['override']])->id, 'value' => null, 'is_checked' => null],
                    'check' => ['option_id' => null, 'value' => null, 'is_checked' => in_array(mb_strtolower($row['override']), ['да', 'есть', 'yes', '1'], true)],
                    default => ['option_id' => null, 'value' => $row['override'], 'is_checked' => null],
                };
            }
            \App\Models\ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id] + $values);
        }
        if ($this->option('hidden')) {
            $product->update(['is_active' => false, 'created_via' => \App\Models\Product::CREATED_VIA_AWAITING_PHOTOS]);
        }
        $this->line(sprintf('audit.onec_card.created=%d:%d:%s', (int) $item?->id, $product->id, $product->slug));

        return self::SUCCESS;
    }

    /** A sibling's text with this variant's differences (--replace=«было=>стало»). */
    private function replaced(?string $text): ?string
    {
        foreach ((array) $this->option('replace') as $pair) {
            [$from, $to] = array_pad(explode('::', str_replace('=>', '::', (string) $pair), 2), 2, null);
            if (filled($from) && $to !== null) {
                $text = str_replace($from, $to, (string) $text);
            }
        }

        return $text;
    }
}
