<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards created from 1C rows that duplicate a card the catalogue already had: same element and
 * every parameter (LinkOneCByParametersCommand::signature) — «Труба L1000 ТМ-Р 430-0.8 D150» and
 * «Труба Теплов и Сухов Моно ТМ-Р L1000 430-0.8 Ø150». With --apply the 1C row moves to the older
 * card and the new one is archived (its URL stays answering with the older card's page).
 *
 *   php artisan integration:onec-duplicates            # list
 *   php artisan integration:onec-duplicates --apply
 */
class OneCCreatedDuplicatesCommand extends Command
{
    protected $signature = 'integration:onec-duplicates
        {--source=onec : Integration source code}
        {--apply : Move the 1C row to the older card and archive the duplicate}';

    protected $description = 'Find (and fold) cards created from 1C that duplicate an older card';

    public function handle(): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $items = IntegrationProduct::query()->where('integration_source_id', $source?->id)->where('match_method', 'manual_created')
            ->whereNotNull('product_id')->get()->keyBy('product_id');
        $created = Product::query()->whereIn('id', $items->keys())->where('is_archived', false)->get(['id', 'name', 'slug', 'price', 'images', 'brand_id']);

        // Every other card by signature (computed once).
        $index = [];
        Product::query()->where('is_archived', false)->whereNotIn('id', $items->keys())
            ->select(['id', 'name', 'slug', 'price', 'images', 'brand_id'])->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$index): void {
                foreach ($chunk as $product) {
                    $signature = LinkOneCByParametersCommand::signature((string) $product->name);
                    if ($signature !== '' && substr_count($signature, '|') >= 3) {
                        $index[$signature][] = $product;
                    }
                }
            });

        $stats = ['created' => $created->count(), 'duplicates' => 0, 'ambiguous' => 0, 'folded' => 0];
        foreach ($created as $new) {
            $signature = LinkOneCByParametersCommand::signature((string) $new->name);
            // The same maker and the same steels: Darco, КПД, Ferrum or Parkanex parts of one size are not duplicates.
            $older = array_values(array_filter($index[$signature] ?? [], fn ($old) => self::sameMaker((string) $new->name, (string) $old->name, $new->brand_id, $old->brand_id)
                && self::steels((string) $new->name) === self::steels((string) $old->name)));
            if ($older === []) {
                continue;
            }
            if (count($older) > 1) {
                $stats['ambiguous']++;
                $this->line(sprintf('audit.onec_duplicates.ambiguous=%d:%s:%s', $new->id, Str::limit($new->slug, 60, ''), collect($older)->pluck('id')->implode(',')));

                continue;
            }
            $old = $older[0];
            $stats['duplicates']++;
            $this->line(sprintf('audit.onec_duplicates.pair=%d:%s:%s:%d:%s:%s:%d', $new->id, Str::limit($new->slug, 60, ''), number_format((float) $new->price, 2, '.', ''),
                $old->id, Str::limit($old->slug, 60, ''), number_format((float) $old->price, 2, '.', ''), (int) ! empty($old->images)));
            if (! $this->option('apply')) {
                continue;
            }
            DB::transaction(function () use ($items, $new, $old): void {
                $item = $items->get($new->id);
                $item->forceFill(['product_id' => $old->id, 'match_method' => 'manual_search', 'target_category_id' => null])->save();
                DB::table('supplier_product_mappings')->where('product_id', $new->id)->update(['product_id' => $old->id, 'updated_at' => now()]);
                Product::query()->whereKey($new->id)->update(['is_archived' => true, 'is_active' => false, 'updated_at' => now()]);
            });
            $stats['folded']++;
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_duplicates.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    private static function sameMaker(string $new, string $old, mixed $newBrand, mixed $oldBrand): bool
    {
        $tis = fn (string $name) => (bool) preg_match('/теплов|(?<!\pL)тис(?!\pL)/iu', $name);
        if ($tis($new) || $tis($old)) {
            return $tis($new) && $tis($old);
        }

        return $newBrand !== null && (int) $newBrand === (int) $oldBrand;
    }

    /** @return array<int,string> steel grades named in the title, sorted */
    private static function steels(string $name): array
    {
        preg_match_all('/(?<!\d)(201|304|316|321|409|430|439)(?!\d)/', $name, $m);
        $steels = array_values(array_unique($m[1]));
        sort($steels);

        return $steels;
    }
}
