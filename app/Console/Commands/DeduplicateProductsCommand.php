<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateProductsCommand extends Command
{
    protected $signature = 'products:deduplicate
        {--apply : Archive duplicates and transfer data (default: dry-run)}
        {--brand= : Filter by brand name}';

    protected $description = 'Find duplicate products (same name+brand), keep the most complete one, archive the rest.';

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $brandName = $this->option('brand');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — duplicates will be archived.</>'
            : '<fg=yellow;options=bold>DRY RUN — nothing will be changed.</>');

        $query = '
            SELECT brand_id, name, COUNT(*) as cnt,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR \',\') as all_ids
            FROM products
            WHERE is_archived = 0
            GROUP BY brand_id, name
            HAVING COUNT(*) > 1
            ORDER BY cnt DESC, name
        ';

        $groups = DB::select($query);

        if ($brandName) {
            $brandId = DB::table('brands')->where('name', 'like', '%' . $brandName . '%')->value('id');
            $groups  = array_filter($groups, fn ($g) => $g->brand_id == $brandId);
        }

        if (empty($groups)) {
            $this->info('No duplicates found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d duplicate groups:', count($groups)));

        $stats = ['groups' => 0, 'archived' => 0, 'sp_transferred' => 0, 'attrs_transferred' => 0];

        foreach ($groups as $group) {
            $allIds = array_map('intval', explode(',', $group->all_ids));

            // Score each product by completeness — pick the best one as canonical
            $scores = [];
            foreach ($allIds as $id) {
                $p = DB::table('products')->where('id', $id)->first();
                $attrCount = DB::table('product_attribute_values')->where('product_id', $id)->count();
                $imgCount  = DB::table('product_images')->where('product_id', $id)->count();
                $scores[$id] =
                    ($attrCount * 2)
                    + ($imgCount * 3)
                    + (empty($p->short_description) ? 0 : 5)
                    + (empty($p->content) ? 0 : 5);
            }
            arsort($scores);
            $keepId  = (int) array_key_first($scores);
            $dupeIds = array_values(array_filter($allIds, fn ($id) => $id !== $keepId));

            $brand = DB::table('brands')->where('id', $group->brand_id)->value('name') ?? '—';
            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>KEEP id=%d (score=%d)</> | archive: [%s] | %s × %s',
                $keepId,
                $scores[$keepId],
                implode(', ', $dupeIds),
                $brand,
                mb_substr($group->name, 0, 70)
            ));

            $stats['groups']++;

            foreach ($dupeIds as $dupeId) {
                // Transfer supplier_products
                $spRows = DB::table('supplier_products')->where('product_id', $dupeId)->get();
                foreach ($spRows as $sp) {
                    $alreadyExists = DB::table('supplier_products')
                        ->where('product_id', $keepId)
                        ->where('supplier_id', $sp->supplier_id)
                        ->exists();

                    if (! $alreadyExists) {
                        $this->line(sprintf('  → transfer supplier_product supplier_id=%d to id=%d', $sp->supplier_id, $keepId));
                        if ($apply) {
                            DB::table('supplier_products')->where('id', $sp->id)->update(['product_id' => $keepId]);
                        }
                        $stats['sp_transferred']++;
                    } else {
                        $this->line(sprintf('  → supplier_id=%d already on keep, deleting from dupe', $sp->supplier_id));
                        if ($apply) {
                            DB::table('supplier_products')->where('id', $sp->id)->delete();
                        }
                    }
                }

                // Transfer attribute values missing on the canonical product
                $keepAttrIds = DB::table('product_attribute_values')
                    ->where('product_id', $keepId)
                    ->pluck('attribute_id')
                    ->toArray();

                $dupeAttrs = DB::table('product_attribute_values')
                    ->where('product_id', $dupeId)
                    ->whereNotIn('attribute_id', $keepAttrIds)
                    ->get();

                foreach ($dupeAttrs as $attr) {
                    $this->line(sprintf('  → transfer attribute_id=%d from id=%d', $attr->attribute_id, $dupeId));
                    if ($apply) {
                        DB::table('product_attribute_values')->where('id', $attr->id)->update(['product_id' => $keepId]);
                    }
                    $stats['attrs_transferred']++;
                }

                // Transfer content/short_description if canonical lacks them
                $keep   = DB::table('products')->where('id', $keepId)->first();
                $dupe   = DB::table('products')->where('id', $dupeId)->first();
                $update = [];
                if (empty($keep->short_description) && ! empty($dupe->short_description)) {
                    $update['short_description'] = $dupe->short_description;
                    $this->line(sprintf('  → transfer short_description from id=%d', $dupeId));
                }
                if (empty($keep->content) && ! empty($dupe->content)) {
                    $update['content'] = $dupe->content;
                    $this->line(sprintf('  → transfer content from id=%d', $dupeId));
                }
                if (! empty($update) && $apply) {
                    DB::table('products')->where('id', $keepId)->update($update);
                }

                // Archive the duplicate
                $this->line(sprintf('  → archive id=%d (score=%d)', $dupeId, $scores[$dupeId]));
                if ($apply) {
                    DB::table('products')->where('id', $dupeId)->update([
                        'is_archived' => true,
                        'is_active'   => false,
                        'updated_at'  => now(),
                    ]);
                }
                $stats['archived']++;
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        if (! $apply && $stats['archived'] > 0) {
            $this->info('Re-run with --apply to archive ' . $stats['archived'] . ' duplicates.');
        }

        return self::SUCCESS;
    }
}
