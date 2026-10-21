<?php

namespace App\Console\Commands;

use App\Models\SupplierChannelTransition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ООО «СанБизнесГруп» was represented by two supplier records:
 * «sanbusinessgroup» (1С catalogue, own stock, order routing) and «ecokamin»
 * (EcoKamin cards and RUB prices from ecokamin.ru, renamed to СанБизнесГруп
 * on 20.10.2026). Every reference of the «ecokamin» record is moved to
 * «sanbusinessgroup»; the old record is kept inactive under a retired code
 * so history stays readable and nothing is cascade-deleted.
 *
 *   php artisan supplier:merge-sanbusinessgroup            # dry run
 *   php artisan supplier:merge-sanbusinessgroup --apply
 */
class MergeSanBusinessGroupSuppliersCommand extends Command
{
    protected $signature = 'supplier:merge-sanbusinessgroup {--apply : Move all references and retire the duplicate record}';

    protected $description = 'Merge the duplicate «ecokamin» supplier record into «sanbusinessgroup»';

    public const TARGET_CODE = 'sanbusinessgroup';

    public const SOURCE_CODE = 'ecokamin';

    public const RETIRED_CODE = 'ecokamin-merged-into-sanbusinessgroup';

    /** Plain foreign keys without uniqueness constraints: table => columns. */
    private const SIMPLE_REFERENCES = [
        'crm_mail_threads' => ['supplier_id'],
        'image_enrichment_logs' => ['supplier_id'],
        'integration_sources' => ['supplier_id'],
        'order_item_fulfillment_histories' => ['supplier_id', 'previous_supplier_id'],
        'order_items' => ['fulfillment_supplier_id', 'supply_supplier_id'],
        'order_settlement_lines' => ['supplier_id'],
        'product_purchase_price_sources' => ['supplier_id'],
        'supplier_auto_transfer_decisions' => ['supplier_id'],
        'supplier_price_imports' => ['supplier_id'],
        'supplier_price_items' => ['supplier_id'],
        'supplier_sources' => ['supplier_id'],
        'supplier_sync_changes' => ['supplier_id'],
        'supplier_sync_states' => ['supplier_id'],
        'supplier_syncs' => ['supplier_id'],
    ];

    /** supplier_code columns: table => column. */
    private const CODE_REFERENCES = [
        'orders' => 'supplier_code',
        'supplier_review_decisions' => 'supplier_code',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $target = DB::table('suppliers')->where('code', self::TARGET_CODE)->first();
        $source = DB::table('suppliers')->where('code', self::SOURCE_CODE)->first();

        if (! $target) {
            $this->error('Поставщик sanbusinessgroup не найден.');
            $this->line('metric.sbg_merge.target_found=0');

            return self::FAILURE;
        }
        if (! $source) {
            $this->info('Дубля ecokamin нет — объединять нечего.');
            $this->line('metric.sbg_merge.source_found=0');

            return self::SUCCESS;
        }

        $targetLegacyDisabled = $this->legacyDisabled((int) $target->id);
        $this->line('metric.sbg_merge.target_id='.$target->id);
        $this->line('metric.sbg_merge.source_id='.$source->id);
        $this->line('metric.sbg_merge.target_active='.(int) $target->is_active);
        $this->line('metric.sbg_merge.source_active='.(int) $source->is_active);
        $this->line('metric.sbg_merge.target_legacy_disabled='.(int) $targetLegacyDisabled);
        $this->line('metric.sbg_merge.source_legacy_disabled='.(int) $this->legacyDisabled((int) $source->id));

        $plan = $this->plan($source, $target, $targetLegacyDisabled);
        foreach ($plan as $key => $value) {
            $this->line("metric.sbg_merge.{$key}={$value}");
        }

        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для объединения добавьте --apply.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->merge($source, $target, $targetLegacyDisabled));
        $this->line('metric.sbg_merge.applied=1');
        $this->line('metric.sbg_merge.remaining_source_links='.DB::table('supplier_products')->where('supplier_id', $source->id)->count());

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function plan(object $source, object $target, bool $targetLegacyDisabled): array
    {
        $links = DB::table('supplier_products')->where('supplier_id', $source->id);
        $conflicts = DB::table('supplier_products as s')
            ->join('supplier_products as t', fn ($join) => $join
                ->on('t.supplier_article', '=', 's.supplier_article')
                ->where('t.supplier_id', '=', $target->id))
            ->where('s.supplier_id', $source->id);

        $plan = [
            'supplier_products' => (clone $links)->count(),
            'supplier_products_linked' => (clone $links)->whereNotNull('product_id')->count(),
            'supplier_products_article_conflicts' => (clone $conflicts)->count(),
            'supplier_products_conflicts_other_product' => (clone $conflicts)->whereColumn('s.product_id', '!=', 't.product_id')->count(),
            'products_with_both_links' => DB::table('supplier_products as s')
                ->join('supplier_products as t', fn ($join) => $join
                    ->on('t.product_id', '=', 's.product_id')
                    ->where('t.supplier_id', '=', $target->id))
                ->where('s.supplier_id', $source->id)->distinct()->count('s.product_id'),
            'links_need_official_source' => $targetLegacyDisabled ? $this->linksNeedingOfficialSource($source)->count() : 0,
            'links_lose_operational' => $targetLegacyDisabled
                ? (clone $links)->where(fn ($q) => $q->whereNull('match_confidence')->orWhereNotIn('match_confidence', ['manual_price_list', 'official_source']))
                    ->where(fn ($q) => $q->whereNull('price_byn')->orWhere('price_byn', '<=', 0))->count()
                : 0,
            'rub_links' => (clone $links)->where('currency', 'RUB')->count(),
            'links_without_ecokamin_url' => (clone $links)->where(fn ($q) => $q->whereNull('source_url')->orWhere('source_url', 'not like', '%ecokamin.ru%'))->count(),
        ];

        foreach (self::SIMPLE_REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    $plan[$table.'__'.$column] = DB::table($table)->where($column, $source->id)->count();
                }
            }
        }
        foreach (self::CODE_REFERENCES as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $plan[$table.'__'.$column] = DB::table($table)->where($column, self::SOURCE_CODE)->count();
            }
        }
        foreach (['brand_supplier', 'supplier_user', 'product_warehouse_stocks', 'supplier_order_requests', 'image_search_failures'] as $table) {
            if (Schema::hasTable($table)) {
                $plan[$table] = DB::table($table)->where('supplier_id', $source->id)->count();
            }
        }
        if (Schema::hasTable('supplier_product_mappings')) {
            $plan['supplier_product_mappings'] = DB::table('supplier_product_mappings')->where('supplier_code', self::SOURCE_CODE)->count();
        }
        $plan['channel_transitions_kept_on_source'] = Schema::hasTable('supplier_channel_transitions')
            ? DB::table('supplier_channel_transitions')->where('supplier_id', $source->id)->count()
            : 0;
        $plan['legacy_products_supplier_id_untouched'] = DB::table('products')->where('supplier_id', $source->id)->count();

        return $plan;
    }

    private function linksNeedingOfficialSource(object $source)
    {
        return DB::table('supplier_products')
            ->where('supplier_id', $source->id)
            ->where(fn ($q) => $q->whereNull('match_confidence')->orWhereNotIn('match_confidence', ['manual_price_list', 'official_source']))
            ->where('price_byn', '>', 0);
    }

    private function merge(object $source, object $target, bool $targetLegacyDisabled): void
    {
        $now = now();
        $from = (int) $source->id;
        $to = (int) $target->id;

        // The target channel may have its legacy path disabled (1С is
        // authoritative). EcoKamin links carry prices from the official
        // ecokamin.ru catalogue, so mark them as such to stay operational.
        if ($targetLegacyDisabled) {
            $this->linksNeedingOfficialSource($source)->update(['match_confidence' => 'official_source', 'updated_at' => $now]);
        }

        foreach (DB::table('supplier_products')->where('supplier_id', $from)->orderBy('id')->get() as $link) {
            $existing = DB::table('supplier_products')
                ->where('supplier_id', $to)
                ->where('supplier_article', $link->supplier_article)
                ->first();
            if ($existing) {
                // Keep the target row; fill gaps from the duplicate and drop it.
                DB::table('supplier_products')->where('id', $existing->id)->update([
                    'product_id' => $existing->product_id ?: $link->product_id,
                    'product_sku' => $existing->product_sku ?: $link->product_sku,
                    'source_url' => $existing->source_url ?: $link->source_url,
                    'updated_at' => $now,
                ]);
                if (Schema::hasTable('supplier_review_decisions')) {
                    DB::table('supplier_review_decisions')->where('supplier_product_id', $link->id)->update(['supplier_product_id' => $existing->id]);
                }
                DB::table('supplier_products')->where('id', $link->id)->delete();

                continue;
            }
            DB::table('supplier_products')->where('id', $link->id)->update(['supplier_id' => $to, 'updated_at' => $now]);
        }

        foreach (self::SIMPLE_REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $from)->update([$column => $to]);
                }
            }
        }
        if (Schema::hasTable('supplier_syncs')) {
            DB::table('supplier_syncs')->where('code', self::SOURCE_CODE)->update(['code' => self::TARGET_CODE, 'name' => $target->name, 'updated_at' => $now]);
        }
        foreach (self::CODE_REFERENCES as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, self::SOURCE_CODE)->update([$column => self::TARGET_CODE]);
            }
        }

        $this->moveUnique('brand_supplier', ['brand_id'], $from, $to);
        $this->moveUnique('supplier_user', ['user_id'], $from, $to);
        $this->moveUnique('product_warehouse_stocks', ['product_id', 'warehouse_code'], $from, $to);
        $this->moveUnique('supplier_order_requests', ['order_id', 'route'], $from, $to);
        $this->moveUnique('image_search_failures', ['product_id'], $from, $to);

        if (Schema::hasTable('supplier_product_mappings')) {
            foreach (DB::table('supplier_product_mappings')->where('supplier_code', self::SOURCE_CODE)->get() as $mapping) {
                $taken = DB::table('supplier_product_mappings')
                    ->where('supplier_code', self::TARGET_CODE)
                    ->where('supplier_article', $mapping->supplier_article)
                    ->exists();
                $taken
                    ? DB::table('supplier_product_mappings')->where('id', $mapping->id)->delete()
                    : DB::table('supplier_product_mappings')->where('id', $mapping->id)->update(['supplier_code' => self::TARGET_CODE]);
            }
        }

        $channels = collect(json_decode((string) ($target->communication_channels ?? '[]'), true) ?: [])
            ->concat(json_decode((string) ($source->communication_channels ?? '[]'), true) ?: [])
            ->unique(fn ($channel) => is_array($channel) ? mb_strtolower(($channel['type'] ?? '').'|'.preg_replace('/\s+/', '', (string) ($channel['value'] ?? ''))) : json_encode($channel))
            ->values()->all();
        DB::table('suppliers')->where('id', $to)->update([
            'is_active' => true,
            'crm_counterparty_id' => $target->crm_counterparty_id ?: $source->crm_counterparty_id,
            'contact' => filled($target->contact) ? $target->contact : $source->contact,
            'communication_channels' => $channels === [] ? null : json_encode($channels, JSON_UNESCAPED_UNICODE),
            'notes' => trim(((string) $target->notes)."\nИмпортёр и поставщик продукции ЭкоКамин (карточки и цены — ecokamin.ru, рубли по курсу НБРБ). Дубль ecokamin объединён ".$now->format('d.m.Y').'.'),
            'updated_at' => $now,
        ]);
        DB::table('suppliers')->where('id', $from)->update([
            'code' => self::RETIRED_CODE,
            'name' => 'ЭкоКамин (объединён с ООО «СанБизнесГруп»)',
            'is_active' => false,
            'crm_counterparty_id' => null,
            'notes' => 'Дубль ООО «СанБизнесГруп». Все связи перенесены в поставщика sanbusinessgroup (id '.$to.') '.$now->format('d.m.Y').'.',
            'updated_at' => $now,
        ]);
    }

    /** Move rows unique per supplier: rows that would collide stay with the target. */
    private function moveUnique(string $table, array $keys, int $from, int $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach (DB::table($table)->where('supplier_id', $from)->get() as $row) {
            $query = DB::table($table)->where('supplier_id', $to);
            foreach ($keys as $key) {
                $query->where($key, $row->{$key});
            }
            $source = DB::table($table)->where('supplier_id', $from);
            foreach ($keys as $key) {
                $source->where($key, $row->{$key});
            }
            $query->exists() ? $source->delete() : $source->update(['supplier_id' => $to]);
        }
    }

    private function legacyDisabled(int $supplierId): bool
    {
        if (! Schema::hasTable('supplier_channel_transitions')) {
            return false;
        }

        return DB::table('supplier_channel_transitions')
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [SupplierChannelTransition::STATUS_LEGACY_DISABLED, SupplierChannelTransition::STATUS_ROLLED_BACK])
            ->orderByDesc('id')
            ->value('status') === SupplierChannelTransition::STATUS_LEGACY_DISABLED;
    }
}
