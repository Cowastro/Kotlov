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
 */
class OneCItemsCommand extends Command
{
    protected $signature = 'integration:onec-items
        {--source=onec : Integration source code}
        {--search=* : Words in the 1C name or code}
        {--cards=* : Words in the site card name}
        {--card-ids=* : Site cards by id}
        {--articles=* : Supplier price rows with this article (which card each feeds)}
        {--link=* : 1C code (or external id) : product id}
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

        foreach ((array) $this->option('articles') as $article) {
            DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->whereRaw('UPPER(TRIM(sp.supplier_article)) = ?', [Str::upper(trim((string) $article))])->limit(10)
                ->get(['sp.id', 's.code', 'sp.product_id', 'sp.price_byn', 'sp.supplier_name'])
                ->each(fn ($row) => $this->line(sprintf('audit.onec_items.article=%s:%s:%d:%s:%s', Str::slug((string) $article) ?: 'none',
                    Str::slug((string) $row->code, '_'), (int) $row->product_id, number_format((float) $row->price_byn, 2, '.', ''),
                    Str::slug(Str::limit((string) $row->supplier_name, 80, '')) ?: 'none')));
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
            if ($item->product_id) {
                $this->line(sprintf('audit.onec_items.already=%d:%d', $item->id, $item->product_id));

                continue;
            }
            if ($this->option('apply') && ! $decision->linkSelectedProduct($item, (int) $productId)) {
                $this->line('audit.onec_items.link_failed='.$item->id);

                continue;
            }
            $linked++;
            $this->line(sprintf('audit.onec_items.linked=%d:%d', $item->id, (int) $productId));
        }
        $this->line('metric.onec_items.linked='.$linked);

        return self::SUCCESS;
    }
}
