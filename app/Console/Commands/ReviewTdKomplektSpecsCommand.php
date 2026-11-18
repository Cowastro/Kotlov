<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektMatcher;
use App\Services\Suppliers\TdKomplektReviewedSpecs;
use App\Services\Suppliers\TdKomplektSpecCompletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewTdKomplektSpecsCommand extends Command
{
    protected $signature = 'supplier:review-td-specs {--apply}';

    protected $description = 'Review the first 30 TD cards: repair proven import duplicates and add verified missing facts';

    public function handle(TdKomplektReviewedSpecs $reviewed, TdKomplektSpecCompletion $completion): int
    {
        $lock = Cache::lock('supplier:sync-td-komplekt', 1800);
        if (! $lock->get()) {
            $this->error('Обмен ТД Комплект уже запущен.');

            return self::FAILURE;
        }
        try {
            $disk = Storage::disk('local');
            $backups = collect($disk->files(TdKomplektFeed::DIRECTORY.'/spec-backups'))->map(fn ($path) => json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR))->groupBy('product_id');
            $items = [];
            foreach ($reviewed->entries() as $article => $entry) {
                $offers = SupplierProduct::with('product')->whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->where('supplier_article', (string) $article)->where('match_status', 'matched')->get();
                if ($offers->count() !== 1 || ! $offers->first()->product) {
                    $items[] = ['article' => $article, 'status' => 'identity_review'];

                    continue;
                }
                $offer = $offers->first();
                $raw = $offer->raw ?? [];
                if ($offer->product->is_archived || ! empty($raw['identity_review']) || ($raw['identity_vendor_code'] ?? '') !== ($raw['vendor_code'] ?? '') || TdKomplektMatcher::brandKey($raw['identity_brand'] ?? '') !== TdKomplektMatcher::brandKey($raw['brand'] ?? '') || ! $reviewed->forRow($raw)) {
                    $items[] = ['article' => $article, 'status' => 'identity_review'];

                    continue;
                }
                $items[] = DB::transaction(function () use ($offer, $raw, $article, $entry, $backups, $completion, $disk) {
                    $p = Product::lockForUpdate()->findOrFail($offer->product_id);
                    $removed = [];
                    $corrected = [];
                    $fieldKey = fn ($name) => $entry['equivalent_fields'][$completion->key($name)] ?? $completion->key($name);
                    foreach ($backups->get($p->id, collect()) as $backup) {
                        foreach ($backup['added'] ?? [] as $added) {
                            // Only reverse an unchanged addition proven by our earlier backup.
                            $prior = collect($backup['before_specs'] ?? []);
                            $existing = $prior->first(fn ($s) => trim((string) ($s['value'] ?? '')) !== '' && $fieldKey($s['key'] ?? '') === $fieldKey($added['key']));
                            if (! $existing || $prior->contains(fn ($s) => $s === $added) || ! in_array($added, $p->specs ?? [], true)) {
                                continue;
                            }
                            $removed[] = $added;
                            if (! $this->option('apply')) {
                                continue;
                            }
                            $path = TdKomplektFeed::DIRECTORY.'/spec-repair-backups/'.now()->format('Ymd-His-u').'-'.$p->id.'.json';
                            if (! $disk->put($path, json_encode(['product_id' => $p->id, 'specs' => $p->specs, 'attributes' => $p->allAttributeValues()->get()->toArray(), 'remove' => $added], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                                throw new \RuntimeException('Не удалось сохранить резервную копию.');
                            }
                            $beforeIds = array_column($backup['before_attributes'] ?? [], 'id');
                            foreach ($p->allAttributeValues()->with('attribute')->whereNotIn('id', $beforeIds)->get() as $value) {
                                if ($completion->matchesUnchangedAddition($value, $added)) {
                                    $value->delete();
                                }
                            }
                            $p->update(['specs' => array_values(array_filter($p->specs, fn ($s) => $s !== $added))]);
                        }
                    }

                    foreach ($entry['corrections'] ?? [] as $correction) {
                        $specs = $p->specs ?? [];
                        foreach ($specs as $index => $spec) {
                            if (($spec['key'] ?? '') !== $correction['key'] || ($spec['value'] ?? null) !== $correction['from']) {
                                continue;
                            }
                            $manualAttribute = $p->allAttributeValues()->whereHas('attribute', fn ($q) => $q->where('name', $correction['key']))->whereNotNull('value')->whereNotIn('value', [$correction['from'], $correction['to'], ''])->exists();
                            if ($manualAttribute) {
                                continue;
                            }
                            $corrected[] = $correction;
                            if (! $this->option('apply')) {
                                continue;
                            }
                            $path = TdKomplektFeed::DIRECTORY.'/spec-repair-backups/'.now()->format('Ymd-His-u').'-'.$p->id.'-correction.json';
                            if (! $disk->put($path, json_encode(['product_id' => $p->id, 'specs' => $p->specs, 'attributes' => $p->allAttributeValues()->get()->toArray(), 'correction' => $correction, 'sources' => $entry['sources']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                                throw new \RuntimeException('Не удалось сохранить резервную копию.');
                            }
                            foreach ($p->allAttributeValues()->with('attribute')->get() as $value) {
                                if ($value->attribute?->name === $correction['key'] && $value->value === $correction['from'] && $value->option_id === null && $value->is_checked === null) {
                                    $value->update(['value' => $correction['to']]);
                                }
                            }
                            $specs[$index]['value'] = $correction['to'];
                            $p->update(['specs' => $specs]);
                        }
                    }

                    return ['article' => (string) $article, 'name' => $p->name, 'removed_proven_duplicates' => $removed, 'corrected_verified_values' => $corrected, 'review' => $entry['review']] + $completion->complete($p, $raw, (bool) $this->option('apply'));
                });
            }
            $path = TdKomplektFeed::DIRECTORY.'/spec-reports/review-'.now()->format('Ymd-His-u').'.json';
            $disk->put($path, json_encode(['apply' => (bool) $this->option('apply'), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->line('products='.count($items));
            foreach (['added', 'removed_proven_duplicates', 'corrected_verified_values', 'conflicts', 'skipped'] as $key) {
                $this->line($key.'='.array_sum(array_map(fn ($item) => count($item[$key] ?? []), $items)));
            }
            $this->line('private_report='.$path);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
