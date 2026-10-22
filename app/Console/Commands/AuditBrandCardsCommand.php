<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Health check of every card of one brand (by brand_id or the brand named in
 * the title): brand set, supplier link, photos, description, specs, category,
 * exact-name duplicates. --apply only sets the missing brand_id; everything
 * else is reported as metric/audit lines (ids and slugs, no free text).
 *
 *   php artisan catalog:audit-brand-cards unipump --supplier=tsk_nasosy
 *   php artisan catalog:audit-brand-cards unipump --supplier=tsk_nasosy --apply
 */
class AuditBrandCardsCommand extends Command
{
    protected $signature = 'catalog:audit-brand-cards {brand : Brand slug} {--supplier= : Supplier code expected to cover the brand} {--apply : Set the brand on cards that name it but have none}';

    protected $description = 'Audit all cards of a brand and fill in a missing brand_id';

    public function handle(): int
    {
        $brand = DB::table('brands')->where('slug', $this->argument('brand'))->first(['id', 'name']);
        if (! $brand) {
            $this->error('Unknown brand.');

            return self::FAILURE;
        }
        $word = mb_strtolower($brand->name);
        $supplierId = $this->option('supplier') ? (int) DB::table('suppliers')->where('code', $this->option('supplier'))->value('id') : 0;
        $brandIds = DB::table('brands')->pluck('id')->all();

        $cards = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_archived', false)
            ->where(fn ($q) => $q->where('p.brand_id', $brand->id)->orWhereRaw('LOWER(p.name) LIKE ?', ['%'.$word.'%']))
            ->orderBy('c.slug')->orderBy('p.name')
            ->get(['p.id', 'p.slug', 'p.name', 'p.brand_id', 'p.is_active', 'p.images', 'p.content', 'p.specs', 'p.price', 'p.availability_status', 'c.slug as category']);

        $stats = ['cards' => $cards->count(), 'active' => 0, 'brand_missing' => 0, 'brand_other' => 0, 'brand_set' => 0, 'supplier_linked' => 0,
            'any_supplier' => 0, 'unlinked_active' => 0, 'no_images' => 0, 'thin_content' => 0, 'no_specs' => 0, 'no_price' => 0, 'duplicate_groups' => 0];
        $linked = $supplierId ? DB::table('supplier_products')->where('supplier_id', $supplierId)->whereNotNull('product_id')->pluck('product_id')->flip() : collect();
        $withAttributes = DB::table('product_attribute_values')->whereIn('product_id', $cards->pluck('id'))->distinct()->pluck('product_id')->flip();
        $anyLink = DB::table('supplier_products')->whereIn('product_id', $cards->pluck('id'))->pluck('product_id')->flip();
        $byName = [];
        $byCategory = [];

        foreach ($cards as $p) {
            $stats['active'] += (int) $p->is_active;
            $byCategory[$p->category ?: 'none'] = ($byCategory[$p->category ?: 'none'] ?? 0) + 1;
            $byName[preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($p->name))][] = $p->id;
            $flags = [];

            if (! $p->brand_id || ! in_array((int) $p->brand_id, $brandIds, true)) {
                $stats['brand_missing']++;
                $flags[] = 'brand';
                if ($this->option('apply')) {
                    DB::table('products')->where('id', $p->id)->update(['brand_id' => $brand->id, 'updated_at' => now()]);
                    $stats['brand_set']++;
                }
            } elseif ((int) $p->brand_id !== (int) $brand->id) {
                $stats['brand_other']++;
                $flags[] = 'other_brand';
            }
            if ($linked->has($p->id)) {
                $stats['supplier_linked']++;
            } elseif ($p->is_active) {
                $stats['unlinked_active']++;
                $flags[] = 'unlinked';
            }
            $stats['any_supplier'] += (int) $anyLink->has($p->id);
            if (count(array_filter((array) json_decode((string) $p->images, true))) === 0) {
                $stats['no_images']++;
                $flags[] = 'images';
            }
            if (mb_strlen(strip_tags((string) $p->content)) < 200) {
                $stats['thin_content']++;
                $flags[] = 'content';
            }
            // The card page shows attribute values; the specs JSON is only one source of them.
            if (! $withAttributes->has($p->id) && count(array_filter((array) json_decode((string) $p->specs, true))) === 0) {
                $stats['no_specs']++;
                $flags[] = 'specs';
            }
            if ((float) $p->price <= 0) {
                $stats['no_price']++;
                $flags[] = 'price';
            }
            $this->line(sprintf('audit.brand_cards.card=%d:%s:%s:%d:%s', $p->id, $p->category ?: 'none', $p->slug, (int) $p->is_active, $flags ? implode('.', $flags) : 'ok'));
        }

        foreach ($byName as $ids) {
            if (count($ids) > 1) {
                $stats['duplicate_groups']++;
                $this->line('audit.brand_cards.duplicate='.implode(',', $ids));
            }
        }
        foreach ($byCategory as $slug => $count) {
            $this->line("audit.brand_cards.category={$slug}:{$count}");
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.brand_cards.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
