<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 1C rows of a source next to the site cards they are, with each card's supplier costs:
 * goods bought from one supplier onto the 1C stock need the purchase price in 1C and
 * a link to the existing card. --link=CODE:PRODUCT_ID links like «Привязать» in the admin.
 *
 *   php artisan integration:onec-items --search=GKB --search=газоанализатор --cards=GKB
 *   php artisan integration:onec-items --link=УТ-00009705:12345 --apply
 *   php artisan integration:onec-items --reject=УТ-00009721 --apply   # wrong candidates off, row to «Не найдены»
 */
class OneCItemsCommand extends Command
{
    protected $signature = 'integration:onec-items
        {--source=onec : Integration source code}
        {--search=* : Words in the 1C name or code}
        {--cards=* : Words in the site card name}
        {--card-ids=* : Site cards by id}
        {--offers=* : Words in the name of supplier price rows without a card}
        {--articles=* : Supplier price rows with this article (which card each feeds)}
        {--link=* : 1C code (or external id) : product id}
        {--relink : A row linked to another card leaves it (with its 1C price there) for the given one}
        {--reject=* : 1C code (or external id) of an unlinked row whose candidates are all wrong}
        {--ignore=* : 1C code (or external id) of an unlinked row that is not for the site (spare part to order, etc.)}
        {--unarchive : With --link: return an archived card to the catalog before linking}
        {--apply : Write the links}';

    protected $description = 'Find 1C rows and the site cards they belong to; link them';

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.onec_items.source=0');

            return self::FAILURE;
        }
        $this->line('audit.onec_items.source='.$source->id.':'.Str::slug($source->partnerName()));

        foreach ((array) $this->option('search') as $word) {
            IntegrationProduct::query()->where('integration_source_id', $source->id)
                ->where(fn ($q) => $q->where('name', 'like', '%'.$word.'%')->orWhere('external_code', 'like', '%'.$word.'%'))
                ->orderBy('external_code')->limit(30)->get()
                ->each(fn (IntegrationProduct $item) => $this->line(sprintf('audit.onec_items.row=%d:%s:%s:%s:%d:%d:%s',
                    $item->id, Str::slug((string) $item->external_code) ?: 'none', Str::slug(Str::limit((string) $item->name, 150, '')),
                    $item->match_status ?: 'none', (int) round((float) $item->price_byn), (int) round((float) $item->stock_quantity), $item->product_id ?: 0)));
        }

        $lookups = array_map(fn ($word) => ['name', $word], (array) $this->option('cards'));
        if ($ids = array_map('intval', (array) $this->option('card-ids'))) {
            $lookups[] = ['id', $ids];
        }
        foreach ($lookups as [$by, $value]) {
            $cards = DB::table('products')->where('is_archived', false)
                ->when($by === 'id', fn ($q) => $q->whereIn('id', $value), fn ($q) => $q->where('name', 'like', '%'.$value.'%'))
                ->orderBy('id')->limit(30)->get(['id', 'slug', 'price', 'is_active']);
            $costs = DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->whereIn('sp.product_id', $cards->pluck('id'))->get(['sp.product_id', 's.code', 'sp.price_byn', 'sp.in_stock'])->groupBy('product_id');
            foreach ($cards as $card) {
                $this->line(sprintf('audit.onec_items.card=%d:%s:%s:%d:%s', $card->id, $card->slug, number_format((float) $card->price, 2, '.', ''), (int) $card->is_active,
                    $costs->get($card->id, collect())->map(fn ($cost) => Str::slug((string) $cost->code, '_').'~'.number_format((float) $cost->price_byn, 2, '.', '').'~'.(int) $cost->in_stock)->implode(',') ?: 'none'));
            }
        }

        foreach ((array) $this->option('offers') as $word) {
            DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')->whereNull('sp.product_id')
                ->where('sp.supplier_name', 'like', '%'.$word.'%')->limit(20)
                ->get(['sp.id', 's.code', 'sp.price_byn', 'sp.supplier_name'])
                ->each(fn ($row) => $this->line(sprintf('audit.onec_items.offer=%d:%s:%s:%s', $row->id, Str::slug((string) $row->code, '_'),
                    number_format((float) $row->price_byn, 2, '.', ''), Str::slug(Str::limit((string) $row->supplier_name, 90, '')) ?: 'none')));
        }

        foreach ((array) $this->option('articles') as $article) {
            DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->whereRaw('UPPER(TRIM(sp.supplier_article)) = ?', [Str::upper(trim((string) $article))])->limit(10)
                ->get(['sp.id', 's.code', 'sp.product_id', 'sp.price_byn', 'sp.supplier_name'])
                ->each(fn ($row) => $this->line(sprintf('audit.onec_items.article=%s:%s:%d:%s:%s', Str::slug((string) $article) ?: 'none',
                    Str::slug((string) $row->code, '_'), (int) $row->product_id, number_format((float) $row->price_byn, 2, '.', ''),
                    Str::slug(Str::limit((string) $row->supplier_name, 80, '')) ?: 'none')));
            DB::table('supplier_product_mappings')->whereRaw('UPPER(TRIM(supplier_article)) = ?', [Str::upper(trim((string) $article))])->limit(10)
                ->get(['supplier_code', 'product_id', 'confidence', 'created_at'])
                ->each(fn ($row) => $this->line(sprintf('audit.onec_items.mapping=%s:%s:%d:%s:%s', Str::slug((string) $article) ?: 'none',
                    Str::slug((string) $row->supplier_code, '_'), (int) $row->product_id, Str::slug((string) $row->confidence, '_') ?: 'none',
                    $row->created_at ? date('Ymd', strtotime((string) $row->created_at)) : '0')));
        }

        $linked = 0;
        foreach ((array) $this->option('link') as $pair) {
            [$code, $productId] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $item = IntegrationProduct::query()->where('integration_source_id', $source->id)
                ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
            if (! $item || (int) $productId <= 0) {
                $this->line('audit.onec_items.link_missing='.Str::slug($code));

                continue;
            }
            if ($item->product_id && (! $this->option('relink') || (int) $item->product_id === (int) $productId)) {
                $this->line(sprintf('audit.onec_items.already=%d:%d', $item->id, $item->product_id));

                continue;
            }
            if ($item->product_id) {
                $this->line(sprintf('audit.onec_items.relink=%d:%d:%d', $item->id, $item->product_id, (int) $productId));
                if ($this->option('apply')) {
                    $this->unlink($item);
                }
            }
            if ($this->option('unarchive')) {
                $archived = DB::table('products')->where('id', (int) $productId)->where('is_archived', true)->exists();
                if ($archived) {
                    $this->line(sprintf('audit.onec_items.unarchived=%d', (int) $productId));
                    if ($this->option('apply')) {
                        DB::table('products')->where('id', (int) $productId)->update(['is_archived' => false, 'is_active' => true, 'updated_at' => now()]);
                    }
                }
            }
            if ($this->option('apply') && ! $decision->linkSelectedProduct($item, (int) $productId)) {
                $this->line('audit.onec_items.link_failed='.$item->id);

                continue;
            }
            $linked++;
            $this->line(sprintf('audit.onec_items.linked=%d:%d', $item->id, (int) $productId));
        }
        $this->line('metric.onec_items.linked='.$linked);
        $this->rejectCandidates($source);
        $this->ignoreRows($source);

        return self::SUCCESS;
    }

    /**
     * Wrong candidates are dropped and the row goes to «Не найдены». The importer keeps that decision
     * (manual_research_unresolved + source identity) until the 1C row itself changes.
     */
    private function rejectCandidates(IntegrationSource $source): void
    {
        $rejected = 0;
        foreach ((array) $this->option('reject') as $code) {
            $item = IntegrationProduct::query()->where('integration_source_id', $source->id)
                ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
            if (! $item || $item->product_id || ! in_array($item->match_status, ['suggested', 'ambiguous', 'unmatched'], true)) {
                $this->line('audit.onec_items.reject_skipped='.Str::slug((string) $code));

                continue;
            }
            $rejectedIds = collect($item->candidates ?? [])->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
            if ($this->option('apply')) {
                $item->forceFill([
                    'product_id' => null,
                    'match_status' => 'unmatched',
                    'match_method' => 'manual_research_unresolved',
                    'match_confidence' => null,
                    'matched_at' => null,
                    'candidates' => [[
                        'type' => 'rejected_candidates',
                        'name' => 'Кандидаты отклонены вручную: карточки нет',
                        'rejected_product_ids' => $rejectedIds,
                        'source_identity' => [
                            'external_id' => $item->external_id,
                            'external_code' => $item->external_code,
                            'external_sku' => $item->external_sku,
                            'barcode' => $item->barcode,
                            'name' => $item->name,
                        ],
                    ]],
                ])->save();
            }
            $rejected++;
            $this->line(sprintf('audit.onec_items.rejected=%d:%d', $item->id, count($rejectedIds)));
        }
        $this->line('metric.onec_items.rejected='.$rejected);
    }

    /** Rows that are not for the site go to «Игнорируется» — the same as the admin bulk action. */
    private function ignoreRows(IntegrationSource $source): void
    {
        $ignored = 0;
        foreach ((array) $this->option('ignore') as $code) {
            $item = IntegrationProduct::query()->where('integration_source_id', $source->id)
                ->where(fn ($q) => $q->where('external_code', $code)->orWhere('external_id', $code))->first();
            if (! $item || $item->product_id || $item->match_status === 'ignored') {
                $this->line('audit.onec_items.ignore_skipped='.Str::slug((string) $code));

                continue;
            }
            if ($this->option('apply')) {
                $item->forceFill(['product_id' => null, 'match_status' => 'ignored', 'match_method' => 'manual_ignore',
                    'match_confidence' => null, 'matched_at' => null])->save();
            }
            $ignored++;
            $this->line('audit.onec_items.ignored='.$item->id);
        }
        $this->line('metric.onec_items.ignored='.$ignored);
    }

    /** The row leaves the wrong card, and so does the 1C price the sync put on it. */
    private function unlink(IntegrationProduct $item): void
    {
        DB::transaction(function () use ($item): void {
            $article = trim((string) ($item->external_sku ?: $item->external_code));
            if ($supplierId = $item->source?->supplier_id) {
                DB::table('supplier_products')->where('product_id', $item->product_id)->where('supplier_id', $supplierId)
                    ->where('supplier_article', $article)->delete();
            }
            $item->forceFill(['product_id' => null, 'match_status' => 'unmatched', 'match_method' => null, 'match_confidence' => null, 'matched_at' => null])->save();
        });
        $item->refresh();
    }
}
