<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the Thermokraft live price rows that kotlov.by had no card for:
 * Термокрафт boilers and stoves, ИПРо GSM modules, room thermostats,
 * Thermagent fluids, circulation pumps and Reflex expansion tanks.
 * Data: resources/data/suppliers/thermokraft-price-cards.json — retail =
 * Thermokraft RRP, technical data and photos from the Onliner catalogue
 * (descriptions written for kotlov.by). supplier:sync-thermokraft links each
 * card to its price row by «sheet_name» and then keeps price and stock.
 *
 *   php artisan catalog:publish-thermokraft-price-cards            # dry run
 *   php artisan catalog:publish-thermokraft-price-cards --apply
 */
class PublishThermokraftPriceCardsCommand extends Command
{
    protected $signature = 'catalog:publish-thermokraft-price-cards {--apply : Create the missing cards}';

    protected $description = 'Create catalogue cards for the Thermokraft price rows that have none';

    public const DATA_FILE = 'data/suppliers/thermokraft-price-cards.json';

    /** Data brand key => existing brand slugs / lower-case names to reuse before creating one. */
    private const BRANDS = [
        'termokraft' => ['slugs' => ['termokraft', 'thermokraft'], 'names' => ['термокрафт', 'termokraft', 'thermokraft'], 'slug' => 'termokraft'],
        'ipro' => ['slugs' => ['ipro'], 'names' => ['ипро', 'ipro'], 'slug' => 'ipro'],
        'cewal' => ['slugs' => ['cewal'], 'names' => ['cewal'], 'slug' => 'cewal'],
        'imit' => ['slugs' => ['imit'], 'names' => ['imit'], 'slug' => 'imit'],
        'thermagent' => ['slugs' => ['thermagent', 'termagent'], 'names' => ['thermagent', 'термагент'], 'slug' => 'thermagent'],
        'ave' => ['slugs' => ['av-engineering', 'avengineering', 'ave'], 'names' => ['av engineering', 'av-engineering', 'ave'], 'slug' => 'av-engineering'],
        'pumpman' => ['slugs' => ['pumpman'], 'names' => ['pumpman'], 'slug' => 'pumpman'],
        'reflex' => ['slugs' => ['reflex', 'reflex-winkelmann'], 'names' => ['reflex', 'reflex winkelmann'], 'slug' => 'reflex'],
        'wilo' => ['slugs' => ['wilo'], 'names' => ['wilo'], 'slug' => 'wilo'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $items = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true, 512, JSON_THROW_ON_ERROR);
        foreach ($items as $item) {
            foreach ($item['images'] as $image) {
                if (! is_file(public_path(ltrim($image, '/')))) {
                    $this->error('Нет изображения: '.$image);

                    return self::FAILURE;
                }
            }
        }

        $keyer = new SyncThermokraftCommand;
        $plan = [];
        foreach ($items as $i => $item) {
            $category = Category::query()->whereIn('slug', $item['category_slugs'])->get(['id', 'slug'])
                ->sortBy(fn (Category $c) => array_search($c->slug, $item['category_slugs'], true))->first();
            $brand = $this->findBrand($item['brand']);
            $existing = Product::query()->where('slug', $item['slug'])->orWhere('name', $item['name'])->first(['id', 'slug']);
            // Same brand and model under another name: report instead of creating a duplicate.
            $twin = null;
            if (! $existing && $brand && ($key = $keyer->modelKey($item['name'], $item['brand_name'])) !== '') {
                $twin = Product::query()->where('brand_id', $brand->id)->where('is_archived', false)->get(['id', 'name', 'slug'])
                    ->first(fn (Product $p) => $keyer->modelKey($p->name, $item['brand_name']) === $key);
            }
            $status = match (true) {
                $existing !== null => 'present',
                $twin !== null => 'twin',
                $category === null => 'no_category',
                default => 'create',
            };
            $plan[] = compact('item', 'category', 'brand', 'existing', 'twin', 'status');
            $this->line(sprintf('audit.thermokraft_cards.item=%d:%s:%s:%s:%s', $i + 1, $status, $category?->slug ?? '',
                $brand?->slug ?? 'new', $existing?->slug ?? $twin?->slug ?? ''));
        }
        foreach (['create', 'present', 'twin', 'no_category'] as $status) {
            $this->line("metric.thermokraft_cards.{$status}=".collect($plan)->where('status', $status)->count());
        }
        $this->line('metric.thermokraft_cards.new_brands='.collect($plan)->where('status', 'create')->whereNull('brand')->pluck('item.brand')->unique()->count());

        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($plan): int {
            $created = 0;
            foreach ($plan as $row) {
                if ($row['status'] !== 'create') {
                    continue;
                }
                $brand = $row['brand'] ?? $this->findBrand($row['item']['brand']) ?? Brand::query()->create([
                    'name' => $row['item']['brand_name'],
                    'slug' => self::BRANDS[$row['item']['brand']]['slug'],
                    'h1' => $row['item']['brand_name'],
                    'country' => $row['item']['country'],
                    'is_active' => true,
                ]);
                $this->createProduct($row['item'], (int) $brand->id, (int) $row['category']->id);
                $created++;
            }

            return $created;
        });
        $this->line("metric.thermokraft_cards.created={$created}");

        return self::SUCCESS;
    }

    private function findBrand(string $key): ?Brand
    {
        $config = self::BRANDS[$key];

        return Brand::query()->whereIn('slug', $config['slugs'])->orderByDesc('is_active')->first()
            ?? Brand::query()->where(function ($q) use ($config) {
                foreach ($config['names'] as $name) {
                    $q->orWhereRaw('LOWER(name) = ?', [$name]);
                }
            })->orderByDesc('is_active')->first();
    }

    private function createProduct(array $item, int $brandId, int $categoryId): void
    {
        Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $item['name'],
            'h1' => $item['name'],
            'slug' => $item['slug'],
            'sku' => $this->uniqueSku($item),
            'price' => $item['price'],
            'price_old' => null,
            'currency' => 'BYN',
            'unit' => 'шт',
            'warranty' => $item['warranty'].' мес.',
            'short_description' => $item['short'],
            'content' => $this->content($item),
            'images' => $item['images'],
            'specs' => collect($item['specs'])->map(fn (array $s) => ['key' => $s[0], 'value' => $s[1]])->values()->all(),
            'service_info' => [
                ['key' => 'Гарантия', 'value' => $item['warranty'].' мес.'],
                ['key' => 'Производитель', 'value' => $item['brand_name'].', '.$item['country']],
                ['key' => 'Поставка', 'value' => 'Со склада официального дистрибьютора в Беларуси'],
            ],
            'content_source_url' => $item['source_url'],
            'meta_title' => Str::limit($item['name'], 60, '').' — купить в %city%',
            'meta_description' => Str::limit($item['short'].' Цена, наличие и доставка по Беларуси.', 175, ''),
            'is_active' => true,
            'is_archived' => false,
            'is_new' => true,
            'in_stock' => false,
            'stock_qty' => null,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
            'manual_stock_management' => false,
        ]);
    }

    private function content(array $item): string
    {
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);
        $html = '<p>'.$e($item['lead']).'</p>'."\n<h3>Особенности</h3>\n<ul>";
        foreach ($item['features'] as $feature) {
            $html .= '<li>'.$e($feature).'</li>';
        }
        $html .= "</ul>\n";
        $html .= match ($item['kind']) {
            'boiler' => "<h3>Подбор и монтаж</h3>\n<p>Мощность котла подбирают по теплопотерям дома: площадь в характеристиках — ориентир для утеплённого здания с потолками до 2,7 м. Котёл устанавливают в помещении с приточной вентиляцией и подключают к дымоходу нужного сечения; при твердотопливном котле в системе обязательна группа безопасности.</p>\n",
            'stove', 'bath' => "<h3>Перед установкой</h3>\n<p>Печь ставят на негорючее основание, соблюдая отступы до сгораемых конструкций по паспорту. Используйте утеплённый дымоход (сэндвич) нужного диаметра и проходные узлы через перекрытия. Поможем подобрать дымоход и комплектующие.</p>\n",
            'fluid' => "<h3>Как рассчитать количество</h3>\n<p>Нужный объём теплоносителя равен объёму системы: котёл, радиаторы, трубы и расширительный бак. Ориентир — около 15 л на 1 кВт мощности котла для систем с радиаторами.</p>\n",
            default => '',
        };

        return $html.'<p>Гарантия производителя — '.$e((string) $item['warranty']).' мес. Цена и наличие обновляются автоматически по прайсу официального дистрибьютора в Беларуси.</p>';
    }

    private function uniqueSku(array $item): string
    {
        $base = $item['sku'] ?: 'THERMOKRAFT-'.mb_strtoupper(substr(sha1($item['sheet_name']), 0, 10));
        $sku = $base;
        $i = 2;
        while (Product::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
