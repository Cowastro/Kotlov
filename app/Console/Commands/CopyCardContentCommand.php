<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One size of a series filled from another: photos, texts and specs of a sibling card, with this size's
 * differences («ф110::ф120») and specs of its own. The target keeps its name, price, category and links.
 */
class CopyCardContentCommand extends Command
{
    protected $signature = 'catalog:copy-card-content
        {from : Card id the content is taken from}
        {to : Card id that gets it}
        {--replace=* : «было::стало» in the copied texts and spec values}
        {--spec=* : «Название: значение» set on the target after the copy}
        {--brand= : Brand name or slug of an existing brand for the target}
        {--apply : Write the content}';

    protected $description = 'Copy photos, description and specs from a sibling card to another size of the series';

    public function handle(): int
    {
        $from = Product::query()->find((int) $this->argument('from'));
        $to = Product::query()->find((int) $this->argument('to'));
        $brand = null;
        if ($brandName = trim((string) $this->option('brand'))) {
            // SQL LOWER() leaves Cyrillic as is on some databases: compare in PHP.
            $brand = Brand::query()->where('name', $brandName)->orWhere('slug', Str::slug($brandName))->first()
                ?? Brand::query()->get(['id', 'name'])->first(fn (Brand $b): bool => mb_strtolower(trim((string) $b->name)) === mb_strtolower($brandName));
        }
        $reason = match (true) {
            ! $from || ! $to => 'no_card',
            $from->id === $to->id => 'same_card',
            $this->option('brand') && ! $brand => 'no_brand',
            default => null,
        };
        if ($reason) {
            $this->line('audit.copy_card.skipped='.(int) $this->argument('to').':'.$reason);

            return self::FAILURE;
        }

        $specs = collect((array) $from->specs)->filter(fn ($row) => is_array($row) && filled($row['key'] ?? null))
            ->map(fn (array $row): array => ['key' => (string) $row['key'], 'value' => $this->replaced((string) ($row['value'] ?? ''))])->values();
        foreach ((array) $this->option('spec') as $spec) {
            [$key, $value] = array_map('trim', array_pad(explode(':', (string) $spec, 2), 2, ''));
            if ($key === '') {
                continue;
            }
            $index = $specs->search(fn (array $row): bool => mb_strtolower($row['key']) === mb_strtolower($key));
            $index === false ? $specs->push(['key' => $key, 'value' => $value]) : $specs->put($index, ['key' => $key, 'value' => $value]);
        }

        $data = array_filter([
            'images' => $from->images ?: null,
            'specs' => $specs->values()->all() ?: null,
            'short_description' => $this->replaced($from->short_description) ?: null,
            'content' => $this->replaced($from->content) ?: null,
            'brand_id' => $brand?->id,
        ], fn ($value) => $value !== null);

        $this->line(sprintf('audit.copy_card.plan=%d:%d:images%d:specs%d:text%d:brand%d', $from->id, $to->id,
            count((array) ($data['images'] ?? [])), count((array) ($data['specs'] ?? [])),
            mb_strlen(strip_tags((string) ($data['content'] ?? ''))), (int) ($brand?->id ?? 0)));
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $to->update($data);
        $this->line('audit.copy_card.done='.$to->id);

        return self::SUCCESS;
    }

    private function replaced(?string $text): ?string
    {
        foreach ((array) $this->option('replace') as $pair) {
            [$was, $now] = array_pad(explode('::', (string) $pair, 2), 2, null);
            if (filled($was) && $now !== null) {
                $text = str_replace($was, $now, (string) $text);
            }
        }

        return $text;
    }
}
