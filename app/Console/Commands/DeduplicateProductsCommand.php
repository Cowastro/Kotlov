<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateProductsCommand extends Command
{
    protected $signature = 'products:deduplicate
        {--apply : Archive duplicates and transfer supplier_products (default: dry-run)}
        {--brand= : Filter by brand name}';

    protected $description = 'Find duplicate products (same name+brand), keep oldest, archive the rest.';

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $brandName = $this->option('brand');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — duplicates will be archived.</>'
            : '<fg=yellow;options=bold>DRY RUN — nothing will be changed.</>');

        $query = '
            SELECT brand_id, name, COUNT(*) as cnt,
                   MIN(id) as keep_id,
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

        $stats = ['groups' => 0, 'archived' => 0, 'sp_transferred' => 0];

        foreach ($groups as $group) {
            $allIds  = array_map('intval', explode(',', $group->all_ids));
            $keepId  = (int) $group->keep_id;
            $dupeIds = array_values(array_filter($allIds, fn ($id) => $id !== $keepId));

            $brand = DB::table('brands')->where('id', $group->brand_id)->value('name') ?? '—';
            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>KEEP id=%d</> | archive: [%s] | %s × %s',
                $keepId,
                implode(', ', $dupeIds),
                $brand,
                mb_substr($group->name, 0, 70)
            ));

            $stats['groups']++;

            foreach ($dupeIds as $dupeId) {
                // Transfer supplier_products to the canonical product
                $spRows = DB::table('supplier_products')->where('product_id', $dupeId)->get();
                foreach ($spRows as $sp) {
                    $alreadyExists = DB::table('supplier_products')
                        ->where('product_id', $keepId)
                        ->where('supplier_id', $sp->supplier_id)
                        ->exists();

                    if (! $alreadyExists) {
                        $this->line(sprintf(
                            '  → transfer supplier_product supplier_id=%d to id=%d',
                            $sp->supplier_id,
                            $keepId
                        ));
                        if ($apply) {
                            DB::table('supplier_products')
                                ->where('id', $sp->id)
                                ->update(['product_id' => $keepId]);
                        }
                        $stats['sp_transferred']++;
                    } else {
                        $this->line(sprintf(
                            '  → supplier_id=%d already on keep product, deleting from dupe',
                            $sp->supplier_id
                        ));
                        if ($apply) {
                            DB::table('supplier_products')->where('id', $sp->id)->delete();
                        }
                    }
                }

                // Transfer product_attribute_values if keep has none
                $keepHasPav = DB::table('product_attribute_values')->where('product_id', $keepId)->exists();
                $dupeHasPav = DB::table('product_attribute_values')->where('product_id', $dupeId)->exists();
                if (! $keepHasPav && $dupeHasPav) {
                    $this->line(sprintf('  → transfer product_attribute_values from id=%d', $dupeId));
                    if ($apply) {
                        DB::table('product_attribute_values')
                            ->where('product_id', $dupeId)
                            ->update(['product_id' => $keepId]);
                    }
                }

                // Archive the duplicate
                $this->line(sprintf('  → archive id=%d', $dupeId));
                if ($apply) {
                    DB::table('products')->where('id', $dupeId)->update([
                        'is_archived'   => true,
                        'is_active'     => false,
                        'updated_at'    => now(),
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
