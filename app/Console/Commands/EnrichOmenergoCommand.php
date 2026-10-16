<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Suppliers\OmenergoCatalog;
use App\Services\Suppliers\OmenergoPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EnrichOmenergoCommand extends Command
{
    protected $signature = 'supplier:enrich-omenergo
        {--dry-run : Inspect source identity without modifying products}
        {--apply : Save verified content and local product photos}
        {--publish-complete : Publish newly created Omenergo cards only after photo/spec checks}
        {--refresh-index : Rebuild supplier catalog index}
        {--limit=20 : Limit cards in a single manual batch}
        {--offset=0 : Skip this many supplier links}
        {--only-missing : Skip complete cards}
        {--report= : Write review CSV}';

    protected $description = 'Manually enrich exact Omenergo models with verified specifications and downloaded photos; never scheduled.';

    public function handle(OmenergoCatalog $catalog, OmenergoPriceList $parser): int
    {
        $supplier = Supplier::where('code', 'omenergo')->where('is_active', true)->first();
        if (! $supplier) {
            $this->error('Set up the Omenergo supplier first.');

            return self::FAILURE;
        }
        $lock = Cache::lock('supplier:enrich-omenergo', 3600);
        if (! $lock->get()) {
            $this->error('Omenergo enrichment already running.');

            return self::FAILURE;
        }
        $stats = ['scanned' => 0, 'updated' => 0, 'published' => 0, 'no_source' => 0, 'identity_mismatch' => 0, 'technical_review' => 0, 'errors' => 0];
        $review = [];
        try {
            $index = $catalog->index((bool) $this->option('refresh-index'));
            $links = $supplier->supplierProducts()->whereNotNull('product_id')->with('product')->orderBy('id')
                ->skip(max(0, (int) $this->option('offset')))->take(max(1, min(300, (int) $this->option('limit'))))->get();
            foreach ($links as $link) {
                $stats['scanned']++;
                $product = $link->product;
                $row = $link->raw;
                if (! $product || ! is_array($row) || ! isset($row['article_key'], $row['brand'], $row['name'])) {
                    $stats['identity_mismatch']++;

                    continue;
                }
                if ($this->option('only-missing') && ! empty($product->images) && count($product->specs ?? []) >= 9 && $product->is_active) {
                    continue;
                }
                try {
                    $entry = $catalog->find($row, $index);
                    if ($entry === null) {
                        $stats['no_source']++;
                        $review[] = [$link->supplier_article, $product->id, $product->name, 'no_unique_source', '', 0, 0];

                        continue;
                    }
                    $page = $catalog->page($entry['url']);
                    if (! $catalog->identityMatches($row, $page)) {
                        $stats['identity_mismatch']++;
                        $review[] = [$link->supplier_article, $product->id, $product->name, 'identity_mismatch', $entry['url'], 0, 0];

                        continue;
                    }
                    $verified = $catalog->verifiedSpecs($row, $page);
                    $page['specs'] = $verified['specs'];
                    if ($verified['conflicts'] !== []) {
                        $stats['technical_review']++;
                    }
                    $review[] = [$link->supplier_article, $product->id, $product->name, $verified['conflicts'] === [] ? 'verified_source' : 'technical_review', $entry['url'], count($page['images']), count($page['specs'])];
                    if (! $this->option('apply') || $this->option('dry-run')) {
                        continue;
                    }
                    Storage::disk('local')->put('omenergo/before-enrichment/'.$product->id.'_'.now()->format('Ymd_His_u').'.json', $product->toJson());
                    $images = [];
                    foreach (array_slice($page['images'], 0, 5) as $i => $image) {
                        $path = $catalog->downloadImage($image, $row['article_key'], $i + 1);
                        if ($path) {
                            $images[] = $path;
                        }
                    }
                    $specs = collect($product->specs ?? [])->filter(fn ($v) => is_array($v) && isset($v['key']))->keyBy('key');
                    foreach (array_merge($parser->specs($row), $page['specs']) as $spec) {
                        $specs->put($spec['key'], $spec);
                    }
                    $product->specs = $specs->values()->all();
                    if ($images !== []) {
                        $product->images = array_values(array_unique(array_merge($images, $product->images ?? [])));
                    }
                    $facts = '<h3>Параметры конкретной модели</h3><table><tbody>';
                    foreach ($page['specs'] as $spec) {
                        $facts .= '<tr><th>'.e($spec['key']).'</th><td>'.e($spec['value']).'</td></tr>';
                    }
                    $facts .= '</tbody></table>';
                    $product->content = $parser->content($row).($page['specs'] !== [] ? $facts : '');
                    $product->content_source_url = $entry['url'];
                    if ($page['documents'] !== []) {
                        $product->documents = array_values(array_unique(array_merge($product->documents ?? [], $page['documents']), SORT_REGULAR));
                    }
                    if ($this->option('publish-complete') && $verified['conflicts'] === [] && str_starts_with((string) $product->sku, 'OMENERGO-')
                        && ! $product->is_archived && ! empty($product->images) && count($page['specs']) >= 3 && (float) $product->price > 0) {
                        if (! $product->is_active) {
                            $stats['published']++;
                        }
                        $product->is_active = true;
                    }
                    $product->save();
                    $link->forceFill(['source_url' => $entry['url']])->save();
                    $stats['updated']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $review[] = [$link->supplier_article, $product->id, $product->name, 'error', '', 0, 0];
                    $this->warn('Omenergo enrichment failed for product #'.$product->id.': '.$e->getMessage());
                }
            }
            $path = $this->option('report') ?: storage_path('app/reports/omenergo/enrichment_'.now()->format('Ymd_His').'.csv');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $f = fopen($path, 'wb');
            fputcsv($f, ['supplier_article', 'product_id', 'product_name', 'action', 'source_url', 'photos', 'specs'], ',', '"', '');
            foreach ($review as $record) {
                fputcsv($f, $record, ',', '"', '');
            }
            fclose($f);
            foreach ($stats as $key => $value) {
                $this->line("metric.omenergo.{$key}={$value}");
            }

            return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
