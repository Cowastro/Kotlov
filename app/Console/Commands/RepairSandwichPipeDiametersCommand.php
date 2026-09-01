<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairSandwichPipeDiametersCommand extends Command
{
    protected $signature = 'catalog:repair-sandwich-pipe-diameters
        {--apply : Write product names and attributes; default is dry-run}';

    protected $description = 'Add missing inner/outer diameters to sandwich pipe product names from product content.';

    private const CATEGORY_SLUG = 'truby-sendvich';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if ($categoryId <= 0) {
            $this->error('Category not found: ' . self::CATEGORY_SLUG);

            return self::FAILURE;
        }

        $stats = [
            'checked' => 0,
            'with_content_pair' => 0,
            'already_ok' => 0,
            'would_update' => 0,
            'updated' => 0,
            'conflicts' => 0,
            'attribute_updates' => 0,
        ];

        $rows = [];

        Product::query()
            ->where('category_id', $categoryId)
            ->where('is_archived', false)
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($apply, &$stats, &$rows): void {
                foreach ($products as $product) {
                    $stats['checked']++;

                    $pair = $this->diameterPairFromContent((string) $product->content);
                    if ($pair === null) {
                        continue;
                    }

                    $stats['with_content_pair']++;
                    $name = html_entity_decode((string) $product->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if ($this->nameHasPair($name, $pair)) {
                        $stats['already_ok']++;
                        $stats['attribute_updates'] += $this->updateDiameterAttributes($product, $pair, $apply);
                        continue;
                    }

                    $existingPair = $this->diameterPairFromName($name);
                    if ($existingPair !== null && $existingPair !== $pair['pair']) {
                        $stats['conflicts']++;
                        $rows[] = [
                            $product->id,
                            'conflict',
                            $existingPair,
                            $pair['pair'],
                            $name,
                            '',
                        ];
                        continue;
                    }

                    $newName = $this->addPairToName($name, $pair['pair']);
                    if ($newName === $name) {
                        continue;
                    }

                    $rows[] = [
                        $product->id,
                        $apply ? 'update' : 'would update',
                        '',
                        $pair['pair'],
                        $name,
                        $newName,
                    ];

                    if ($apply) {
                        $updates = ['name' => $newName, 'updated_at' => now()];
                        foreach (['h1', 'meta_title'] as $field) {
                            $current = html_entity_decode((string) ($product->{$field} ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            if ($current === '' || $current === $name) {
                                $updates[$field] = $newName;
                            }
                        }

                        $product->forceFill($updates)->save();
                        $stats['updated']++;
                    } else {
                        $stats['would_update']++;
                    }

                    $stats['attribute_updates'] += $this->updateDiameterAttributes($product, $pair, $apply);
                }
            });

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: sandwich pipe diameters were written.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database changes.</>');

        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        if ($rows !== []) {
            $this->table(['id', 'status', 'name_pair', 'content_pair', 'old_name', 'new_name'], array_slice($rows, 0, 50));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{inner:string, outer:string, pair:string}|null
     */
    private function diameterPairFromContent(string $content): ?array
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (! preg_match(
            '/Диаметр\s*[:：]\s*(\d{2,3})\s*мм\s*\((?:внутренн|внутр)[^)]+\)\s*(?:,|и)\s*(\d{2,3})\s*мм\s*\((?:внешн|наруж)[^)]+\)/iu',
            $text,
            $match
        )) {
            return null;
        }

        return [
            'inner' => $match[1],
            'outer' => $match[2],
            'pair' => $match[1] . '/' . $match[2],
        ];
    }

    private function nameHasPair(string $name, array $pair): bool
    {
        return (bool) preg_match('/(?:Ø|⌀|Ф|ф)?\s*' . preg_quote($pair['pair'], '/') . '\b/u', $name);
    }

    private function diameterPairFromName(string $name): ?string
    {
        return preg_match('/(?:Ø|⌀|Ф|ф)\s*(\d{2,3})\s*\/\s*(\d{2,3})\b/u', $name, $match)
            ? $match[1] . '/' . $match[2]
            : null;
    }

    private function addPairToName(string $name, string $pair): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $name);

        if (preg_match('/\bL\d{2,4}\b/u', $name)) {
            return trim(preg_replace('/\b(L\d{2,4})\b/u', 'Ø ' . $pair . ' $1', $name, 1) ?? $name);
        }

        return Str::of($name)->replaceMatches('/\s+/', ' ')->append(' Ø ' . $pair)->toString();
    }

    /**
     * @param array{inner:string, outer:string, pair:string} $pair
     */
    private function updateDiameterAttributes(Product $product, array $pair, bool $apply): int
    {
        $updates = 0;

        $updates += $this->updateAttributeValue($product, 'Диаметр', $pair['inner'], $apply);
        $updates += $this->updateAttributeValue($product, 'Внешний диаметр', $pair['outer'], $apply);

        return $updates;
    }

    private function updateAttributeValue(Product $product, string $attributeName, string $value, bool $apply): int
    {
        $row = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('pav.product_id', $product->id)
            ->where('a.name', $attributeName)
            ->first(['pav.id', 'pav.value']);

        if (! $row) {
            return 0;
        }

        $current = trim((string) $row->value);
        if ($current !== '' && $current !== $value) {
            return 0;
        }

        if ($current === $value) {
            return 0;
        }

        if ($apply) {
            DB::table('product_attribute_values')
                ->where('id', (int) $row->id)
                ->update(['value' => $value, 'updated_at' => now()]);
        }

        return 1;
    }
}
