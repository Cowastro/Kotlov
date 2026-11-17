<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Models\SupplierReviewDecision;
use App\Services\Suppliers\SupplierOfferCards;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ReviewTdKomplektRemainingCommand extends Command
{
    // Same manufacturer article; retain the populated canonical card. Do not merge
    // the other Bereg offer/card and accidentally add duplicate supplier balances.
    private const LINKS = [
        '1637569' => [35777, 'LD.65.501.15', [35777, 38337]],
        '1637556' => [35751, 'LD.65.504.15', [35751, 38153]],
        '1727397' => [35787, 'LD.65.505.15', [35787, 38015]],
    ];

    protected $signature = 'supplier:review-td-komplekt-remaining {--apply : Create hidden drafts for reviewed clearance goods}';

    protected $description = 'Reconcile the original 614 TD Komplekt review rows, preserving unresolved identifiers';

    public function handle(): int
    {
        $lock = Cache::lock('supplier:sync-td-komplekt', 1800);
        if (! $lock->get()) {
            $this->error('Обмен ТД Комплект уже запущен.');

            return self::FAILURE;
        }
        try {
            $manifest = json_decode(file_get_contents(resource_path('data/td-komplekt-review-2026-11-17.json')), true, 512, JSON_THROW_ON_ERROR);
            $clearance = collect($manifest['clearance'])->keyBy('id');
            $matcher = new TdKomplektMatcher;
            $feed = new TdKomplektFeed;
            $categories = Category::pluck('id', 'slug');
            $rows = [];
            $offers = SupplierProduct::with(['supplier', 'product.category'])->whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))
                ->whereIn('source_wp_id', $manifest['original_ids'])->orderBy('source_wp_id')->get();
            foreach ($offers as $offer) {
                $raw = $offer->raw ?? [];
                $review = $clearance->get((string) $offer->source_wp_id);
                $category = $review['category'] ?? $feed->category($raw);
                $status = null;
                $action = $review && $review['disposition'] === 'clearance_draft'
                    ? 'Распродажа поставщика: проверить актуальность, комплектацию и фото перед публикацией' : '';
                if (! $offer->product_id && isset(self::LINKS[(string) $offer->source_wp_id])) {
                    [$productId, $article, $candidates] = self::LINKS[(string) $offer->source_wp_id];
                    $match = $matcher->match($raw);
                    $actual = $match['candidates'];
                    sort($actual);
                    sort($candidates);
                    $product = Product::with('brand', 'category')->find($productId);
                    if ($actual === $candidates && $offer->supplier_article === $article && $product
                        && ! $product->is_archived && ! $product->manual_stock_management && $product->brand?->name === 'LD'
                        && $product->category?->slug === 'rezbovye-fitingi') {
                        $status = 'canonical_link_planned';
                        $action = 'Связать с полной карточкой того же заводского артикула; другие остатки не объединять';
                        if ($this->option('apply')) {
                            $decision = SupplierReviewDecision::firstOrCreate(['decision_key' => 'td-komplekt-reviewed-20261117-'.$offer->source_wp_id], [
                                'supplier_code' => 'td_komplekt', 'decision' => SupplierReviewDecision::DECISION_LINK,
                                'report_file' => 'td-komplekt-review-2026-11-17.json', 'report_row' => (string) $offer->source_wp_id,
                                'status' => SupplierReviewDecision::STATUS_PENDING, 'supplier_product_id' => $offer->id,
                                'product_id' => $productId, 'supplier_title' => $offer->supplier_name,
                                'supplier_article' => $article, 'reason' => $action, 'payload' => ['reviewed_candidates' => $candidates],
                            ]);
                            if ($this->call('supplier:apply-review-decisions', ['--supplier' => 'td_komplekt', '--id' => [$decision->id], '--apply' => true]) !== self::SUCCESS) {
                                return self::FAILURE;
                            }
                            $offer->refresh()->load('product.category');
                        }
                    }
                }
                if ($review && ! $offer->product_id) {
                    if (TdKomplektFeed::key($offer->supplier_article) !== TdKomplektFeed::key($review['article'])
                        || TdKomplektMatcher::brandKey($raw['brand'] ?? '') !== TdKomplektMatcher::brandKey($review['brand'])
                        || TdKomplektFeed::key($offer->supplier_name) !== TdKomplektFeed::key($review['name'])) {
                        $status = 'source_identity_changed';
                        $action = 'Повторно сверить изменившееся название, бренд и артикул';
                    } elseif ($review['disposition'] === 'display_equipment') {
                        $status = 'display_equipment';
                        $action = 'Торговое оборудование: не публиковать как сантехнику';
                    } elseif ($category && isset($categories[$category])) {
                        $match = $matcher->match($raw);
                        if ($match['product_id'] || ! in_array($match['reason'], ['unmatched', 'foreign_brand_article'], true)) {
                            $status = 'identifier_review';
                        } elseif ($this->option('apply')) {
                            $product = app(SupplierOfferCards::class)->create($offer->id, $categories[$category]);
                            $product->update(['created_via' => 'supplier_review']);
                            $offer->refresh()->load('product.category');
                        } else {
                            $status = 'clearance_draft_planned';
                        }
                        $action = 'Распродажа поставщика: проверить актуальность, комплектацию и фото перед публикацией';
                    }
                }
                if ($offer->product_id) {
                    $status = $offer->product?->is_active ? 'published' : 'draft';
                    if (! $offer->product?->is_active && $action === '') {
                        $action = 'Проверить карточку и добавить недостающую фотографию перед публикацией';
                    }
                }
                if (! $status) {
                    $status = $offer->match_confidence ?: 'data_review';
                    $action = match ($status) {
                        'duplicate_feed_barcode' => 'Запросить верный EAN для каждого артикула; проверить упаковку и смену заводского артикула',
                        'variant_unconfirmed' => 'Подтвердить полное исполнение с суффиксом; не объединять по похожему названию',
                        'ambiguous' => 'Проверить оставшиеся карточки-кандидаты одного артикула',
                        'brand_unconfirmed' => 'Подтвердить бренд и заводской артикул',
                        default => trim($raw['brand'] ?? '') === '' ? 'Запросить бренд производителя' : 'Запросить заводской артикул или подтверждённый уникальный EAN',
                    };
                }
                $rows[] = ['source_id' => (string) $offer->source_wp_id, 'article' => $offer->supplier_article,
                    'barcode' => $raw['barcode'] ?? '', 'source_has_photo' => TdKomplektFeed::image($raw['media_img'] ?? '') !== null,
                    'brand' => $raw['brand'] ?? '', 'name' => $offer->supplier_name, 'category' => $category,
                    'product_id' => $offer->product_id, 'status' => $status, 'action' => $action,
                    'url' => $offer->product?->is_active ? url('/'.$offer->product->category?->slug.'/'.$offer->product->slug) : null];
            }
            $counts = array_count_values(array_column($rows, 'status'));
            Storage::disk('local')->put(TdKomplektFeed::DIRECTORY.'/remaining-review.json', json_encode(['counts' => $counts, 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            foreach ($counts as $status => $count) {
                $this->line("metric.td_review.{$status}={$count}");
            }
            $this->line('metric.td_review.total='.count($rows));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
