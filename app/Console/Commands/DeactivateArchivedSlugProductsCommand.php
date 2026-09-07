<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deactivate products whose slug ends with "-archived-{id}" pattern.
 *
 * These products were previously processed by products:deduplicate --fix-slugs
 * which renames the duplicate's slug but may leave is_active = true.
 * This command corrects that inconsistency.
 *
 * Usage:
 *   php artisan products:deactivate-archived-slugs          # dry-run
 *   php artisan products:deactivate-archived-slugs --apply  # write changes
 */
class DeactivateArchivedSlugProductsCommand extends Command
{
    protected $signature = 'products:deactivate-archived-slugs
                            {--apply : Write changes to DB (default: dry-run)}';

    protected $description = 'Deactivate products with -archived-{id} in slug that are still is_active=true';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — товары будут деактивированы.</>'
            : '<fg=yellow;options=bold>DRY RUN — изменений не будет.</>');

        // Match slug ending with -archived-{digits}
        $affected = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->whereRaw("p.slug REGEXP '-archived-[0-9]+$'")
            ->where('p.is_active', true)
            ->select([
                'p.id',
                'p.slug',
                'p.name',
                'p.is_archived',
                DB::raw("IFNULL(b.name,'—') as brand"),
            ])
            ->orderBy('b.name')
            ->orderBy('p.slug')
            ->get();

        if ($affected->isEmpty()) {
            $this->info('Нет активных товаров с -archived- в slug. Всё чисто.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Найдено %d активных товаров с "-archived-{id}" в slug:', $affected->count()));
        $this->newLine();

        $byBrand = $affected->groupBy('brand');
        foreach ($byBrand as $brand => $rows) {
            $this->comment(sprintf('  %s (%d):', $brand, $rows->count()));
            foreach ($rows as $r) {
                $alreadyArchived = $r->is_archived ? ' [уже is_archived=1]' : '';
                $this->line(sprintf('    id=%-6d  %s%s', $r->id, $r->slug, $alreadyArchived));
            }
        }

        $this->newLine();
        $this->line(sprintf('Итого: %d товаров → is_active=false, is_archived=true', $affected->count()));

        if (! $apply) {
            $this->newLine();
            $this->warn('Запустите с --apply чтобы применить изменения.');
            return self::SUCCESS;
        }

        $ids = $affected->pluck('id')->all();

        DB::table('products')->whereIn('id', $ids)->update([
            'is_active'   => false,
            'is_archived' => true,
            'updated_at'  => now(),
        ]);

        $this->info(sprintf('✓ Деактивировано %d товаров.', count($ids)));

        return self::SUCCESS;
    }
}
