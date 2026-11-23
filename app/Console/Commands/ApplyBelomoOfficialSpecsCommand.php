<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Catalog\BelomoOfficialAssets;
use App\Support\Catalog\BelomoOfficialMeterSpecs;
use App\Support\Catalog\BelomoSelectorLineup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ApplyBelomoOfficialSpecsCommand extends Command
{
    protected $signature = 'catalog:apply-belomo-official-specs {--apply : Записать подтверждённые характеристики и документы}';

    protected $description = 'Характеристики семи серий БелОМО и локальные документы без изменения связей, цен и остатков';

    public static function explicitDistance(?string $text, ?int $distance): ?string
    {
        if (! $text || ! $distance) {
            return $text;
        }

        return preg_replace('/\bL\s*=\s*\d+\s*(?:мм)?/ui', 'межосевое расстояние '.$distance.' мм', $text);
    }

    public function handle(): int
    {
        try {
            return DB::transaction(function (): int {
                BelomoOfficialAssets::verifyFiles();
                $products = Product::whereHas('category', fn ($q) => $q->where('slug', 'schetchiki-gaza'))
                    ->active()->notArchived()->with('supplierProducts')->lockForUpdate()->get();
                $plan = [];
                foreach (BelomoOfficialMeterSpecs::all() as $article => $profile) {
                    $model = ['article_compact' => str_replace(['.', '-'], '', $article)];
                    $matches = $products->filter(fn ($p) => BelomoSelectorLineup::matchesProduct($p, $model));
                    if ($matches->count() !== 1) {
                        throw new RuntimeException('Неоднозначная или отсутствующая карточка '.$article.' — изменения отменены.');
                    }
                    $product = $matches->first();
                    $sources = collect(BelomoOfficialAssets::all())->flatMap(fn ($s) => $s['documents'])->keyBy('source_url');
                    $documents = collect((array) $product->documents)->filter()->map(function ($doc) use ($sources) {
                        $url = is_array($doc) ? ($doc['url'] ?? '') : $doc;

                        return $sources->has($url) ? ['label' => $sources[$url]['label'], 'url' => $sources[$url]['url']] : $doc;
                    })->values();
                    foreach ($profile['documents'] as $document) {
                        if (! $documents->contains(fn ($d) => (is_array($d) ? ($d['url'] ?? '') : $d) === $document['url'])) {
                            $documents->push(['label' => $document['label'], 'url' => $document['url']]);
                        }
                    }
                    $plan[$product->id] = ['product' => $product, 'updates' => ['name' => $profile['name'], 'h1' => $profile['name'],
                        'specs' => $profile['specs'], 'documents' => $documents->unique(fn ($d) => is_array($d) ? ($d['url'] ?? '') : $d)->values()->all(),
                        'images' => $profile['images'], 'content_source_url' => $profile['source'],
                        'content' => $profile['content'], 'short_description' => 'БелОМО '.$profile['model'].'. '.($profile['distance'] ? 'Межосевое расстояние '.$profile['distance'].' мм. ' : '').'Заводской артикул '.$article.'.',
                        'meta_title' => $profile['name'].' — купить в Беларуси',
                        'meta_description' => 'БелОМО '.$profile['model'].': '.($profile['distance'] ? 'межосевое расстояние '.$profile['distance'].' мм, ' : '').'характеристики, фото и документы производителя. Подбор и доставка по Беларуси.']];
                }
                foreach (BelomoSelectorLineup::models() as $model) {
                    if (! $model['length']) {
                        continue;
                    }
                    $matches = $products->filter(fn ($p) => BelomoSelectorLineup::matchesProduct($p, $model));
                    if ($matches->count() !== 1 || isset($plan[$matches->first()->id])) {
                        continue;
                    }
                    $product = $matches->first();
                    $updates = [];
                    foreach (['name', 'h1', 'content', 'short_description', 'meta_title', 'meta_description'] as $field) {
                        $new = self::explicitDistance($product->$field, $model['length']);
                        if ($new !== $product->$field) {
                            $updates[$field] = $new;
                        }
                    }
                    if ($updates) {
                        $plan[$product->id] = compact('product', 'updates');
                    }
                }
                $this->line('official_profiles='.count(BelomoOfficialMeterSpecs::all()).'; cards_to_check='.count($plan));
                if (! $this->option('apply')) {
                    return self::SUCCESS;
                }
                $path = 'belomo-official-backups/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                if (! Storage::disk('local')->put($path, json_encode($products->map->getAttributes(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION))) {
                    throw new RuntimeException('Резервная копия не сохранена — изменения отменены.');
                }
                foreach ($plan as $row) {
                    // MySQL keeps JSON keys in its own order: the same specs, documents or images are not a change
                    foreach (['specs', 'documents', 'images'] as $json) {
                        if (array_key_exists($json, $row['updates']) && self::sameJson($row['product']->{$json}, $row['updates'][$json])) {
                            unset($row['updates'][$json]);
                        }
                    }
                    $row['product']->fill($row['updates']);
                    if ($row['product']->isDirty()) {
                        $row['product']->save();
                    }
                }
                $this->info('Характеристики и документы обновлены. Цены, остатки и связи не менялись. Копия: '.$path);

                return self::SUCCESS;
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** The same value whatever the order of the keys of its objects. */
    private static function sameJson(mixed $a, mixed $b): bool
    {
        $canon = function (mixed $v) use (&$canon): mixed {
            if (! is_array($v)) {
                return $v;
            }
            $v = array_map($canon, $v);
            if (! array_is_list($v)) {
                ksort($v);
            }

            return $v;
        };

        return $canon($a) == $canon($b);
    }
}
