<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\OneCItem;
use App\Models\Product;
use App\Support\Catalog\BelomoSelectorLineup;
use App\Support\Catalog\EliconGasMeterCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReconcileEliconCatalogCommand extends Command
{
    public const BINDINGS = [
        'УТ-00003121' => ['6b6c7b9d-32a5-11ea-a220-1c1b0d1de7c2', '8181.00.00.000-10'],
        'УТ-00009758' => ['f479c630-e3f5-11f1-8c60-1c1b0d1de7c2', '8181.00.00.000-24'],
        'УТ-00009759' => ['5851eca0-e3f6-11f1-8c60-1c1b0d1de7c2', '8072.00.00.000-21'],
        'УТ-00009760' => ['7ed786f0-e3f6-11f1-8c60-1c1b0d1de7c2', '8072.00.00.000-23'],
        'УТ-00003122' => ['6b6c7b9e-32a5-11ea-a220-1c1b0d1de7c2', '8181.00.00.000-00'],
    ];

    protected $signature = 'supplier:reconcile-elicon-catalog {--apply : Применить подтверждённые изменения}';

    protected $description = 'Сверить 41 счётчик Эликон и пять точных связей 1С без обновления цен и остатков';

    public function handle(): int
    {
        try {
            return DB::transaction(function (): int {
                $category = Category::where('slug', 'schetchiki-gaza')->firstOrFail();
                $brand = Brand::where('slug', 'belomo')->firstOrFail();
                $products = Product::where('category_id', $category->id)->with('supplierProducts')->lockForUpdate()->get();
                $lineup = collect(BelomoSelectorLineup::models())->keyBy('article_label');
                $plan = [];
                foreach (EliconGasMeterCatalog::models() as $row) {
                    $model = $lineup->get($row['full_article']);
                    if (! $model) {
                        throw new RuntimeException('Нет параметров подбора: '.$row['full_article']);
                    }
                    $candidates = $products->filter(fn ($product) => BelomoSelectorLineup::matchesProduct($product, $model));
                    if ($candidates->count() > 1) {
                        throw new RuntimeException('Несколько карточек для '.$row['full_article'].' — автоматическое изменение запрещено.');
                    }
                    $product = $candidates->first();
                    if (! $product && $row['full_article'] !== '8336.00.00.000-58') {
                        throw new RuntimeException('Неожиданно отсутствует '.$row['full_article'].' — нужна проверка.');
                    }
                    if ($product && (! $product->is_active || $product->is_archived)) {
                        throw new RuntimeException('Карточка скрыта: '.$row['full_article'].' — нужна проверка.');
                    }
                    $plan[] = compact('row', 'model', 'product');
                }
                $pendingArticle = '8181.00.00.000-24';
                $pendingMatches = $products->filter(fn ($p) => BelomoSelectorLineup::matchesProduct($p, ['article_compact' => str_replace(['.', '-'], '', $pendingArticle)]));
                if ($pendingMatches->count() > 1) {
                    throw new RuntimeException('Несколько карточек 8181-24.');
                }
                $pending = $pendingMatches->first();
                $bindings = [];
                foreach (self::BINDINGS as $code => [$key, $article]) {
                    $item = OneCItem::where('ref_key', $key)->where('code', $code)->lockForUpdate()->firstOrFail();
                    $link = IntegrationProduct::whereHas('source', fn ($q) => $q->where('code', 'onec'))->where('external_id', $key)->lockForUpdate()->sole();
                    $target = $article === $pendingArticle ? $pending : collect($plan)->first(fn ($p) => $p['row']['full_article'] === $article)['product'];
                    if (($link->product_id && $link->product_id !== $target?->id)
                        || ($target && IntegrationProduct::whereHas('source', fn ($q) => $q->where('code', 'onec'))
                            ->where('product_id', $target->id)->where('id', '!=', $link->id)->where('match_status', '!=', 'ignored')->exists())) {
                        throw new RuntimeException('Конфликт связи 1С '.$article.' — изменения отменены.');
                    }
                    $bindings[] = compact('link', 'target', 'article');
                }
                $this->line('models=41; missing='.collect($plan)->whereNull('product')->count().'; pending_8181_24='.($pending ? 0 : 1));
                if (! $this->option('apply')) {
                    return self::SUCCESS;
                }
                $backup = 'elicon-catalog-repairs/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                if (! Storage::disk('local')->put($backup, json_encode(['products' => $products->map->getAttributes(), 'links' => collect($bindings)->map(fn ($b) => $b['link']->getAttributes())], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) {
                    throw new RuntimeException('Не удалось сохранить резервную копию.');
                }
                foreach ($plan as $entry) {
                    ['row' => $row, 'model' => $model, 'product' => $product] = $entry;
                    // Only the absent, explicitly verified execution may be created. No prices or stock inferred.
                    if (! $product) {
                        $product = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id,
                            'sku' => 'BELOMO-8336-58', 'slug' => 'schetchik-gaza-belomo-sgd-4-3-1-g4-8336-58',
                            'name' => $row['name'], 'h1' => $row['name'], 'price' => 0, 'currency' => 'BYN', 'unit' => 'шт',
                            'is_active' => true, 'is_archived' => false, 'in_stock' => false,
                            'availability_status' => 'check', 'requires_price_confirmation' => true,
                            'content_source_url' => $row['url'],
                            'images' => ['/img/products/elicon/8336-58-official.png'],
                            'content' => 'Диафрагменный счётчик газа БелОМО СГД-4-3-1-G4 для бытового учёта газа. Левое исполнение, расстояние между патрубками 110 мм. Перед заменой сверьте исполнение и подключение с проектом. Поможем уточнить комплектацию и срок поставки.',
                            'meta_title' => 'Счётчик газа БелОМО СГД-4-3-1-G4 8336-58, левый 110 мм — купить',
                            'meta_description' => 'БелОМО СГД-4-3-1-G4, артикул 8336-58: диафрагменный счётчик газа, левое исполнение, 110 мм. Подбор и доставка по Беларуси.']);
                    }
                    $specs = (array) $product->specs;
                    $hasArticle = collect($specs)->contains(fn ($s, $key) => (is_array($s) ? ($s['key'] ?? '') : $key) === 'Артикул производителя');
                    if (! $hasArticle) {
                        if (array_is_list($specs)) {
                            $specs[] = ['key' => 'Артикул производителя', 'value' => $row['full_article'], 'unit' => ''];
                        } else {
                            $specs['Артикул производителя'] = $row['full_article'];
                        }
                    }
                    $official = \App\Support\Catalog\BelomoOfficialMeterSpecs::all()[$row['full_article']] ?? null;
                    $name = $official['name'] ?? ApplyBelomoOfficialSpecsCommand::explicitDistance(preg_replace('/-\s+(G\d)/u', '-$1', $row['name']), $model['length']);
                    $updates = ['name' => $name, 'h1' => $name, 'specs' => $specs];
                    $product->fill($updates);
                    if ($product->isDirty()) {
                        $product->save();
                    }
                }
                if (! $pending) {
                    // Identity is supplied by the purchase document. No unverified mounting/flow data enter selection.
                    $pending = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id,
                        'sku' => 'BELOMO-8181-24', 'slug' => 'schetchik-gaza-belomo-8181-24',
                        'name' => 'Счётчик газа БелОМО 8181.00.00.000-24 (8181-24)', 'h1' => 'Счётчик газа БелОМО 8181-24',
                        'price' => 0, 'currency' => 'BYN', 'unit' => 'шт', 'is_active' => true, 'is_archived' => false,
                        'in_stock' => false, 'availability_status' => 'check', 'requires_price_confirmation' => true,
                        'specs' => ['Артикул производителя' => $pendingArticle],
                        'content' => 'Счётчик газа БелОМО, заводской артикул 8181.00.00.000-24. Уточним исполнение, подключение и комплектацию перед заказом. Для подбора замены сообщите маркировку установленного счётчика.',
                        'meta_title' => 'Счётчик газа БелОМО 8181-24 — купить с доставкой по Беларуси',
                        'meta_description' => 'Счётчик газа БелОМО 8181.00.00.000-24. Помощь с подбором исполнения и комплектации. Наличие и срок поставки уточняются перед заказом.']);
                }
                foreach ($bindings as $binding) {
                    $link = $binding['link'];
                    $target = $binding['article'] === $pendingArticle ? $pending : $binding['target'];
                    if (! $link->product_id) {
                        $link->forceFill(['product_id' => $target->id, 'match_status' => 'matched', 'match_method' => 'manual_elicon_article',
                            'match_confidence' => 1, 'matched_at' => now()])->save();
                    }
                }
                $this->info('Сверено 41 исполнение; цены и остатки не изменены. Копия: '.$backup);

                return self::SUCCESS;
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
