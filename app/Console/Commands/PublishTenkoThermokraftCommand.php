<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TENKO electric boilers and heating elements that the Thermokraft live
 * price (supplier:sync-thermokraft) lists but kotlov.by had no card for.
 * Data: resources/data/suppliers/tenko-thermokraft-2026-10-22.json — retail
 * = Thermokraft RRP of 22.10.2026, technical data and photos from the
 * official TENKO catalogue tenko.by (descriptions written for kotlov.by).
 *
 * The command also consolidates the brand: products on duplicate TENKO
 * brand records move to /brands/tenko, the duplicates are deactivated, and
 * the brand page gets its logo and description. Supplier offers, stock and
 * hourly price updates come from supplier:sync-thermokraft (model-key match).
 *
 *   php artisan catalog:publish-tenko-thermokraft            # dry run
 *   php artisan catalog:publish-tenko-thermokraft --apply
 */
class PublishTenkoThermokraftCommand extends Command
{
    protected $signature = 'catalog:publish-tenko-thermokraft {--apply : Create cards and update the TENKO brand}';

    protected $description = 'Create the TENKO cards missing from the Thermokraft price and consolidate the TENKO brand page';

    public const BRAND_SLUG = 'tenko';

    public const DATA_FILE = 'data/suppliers/tenko-thermokraft-2026-10-22.json';

    private const BROKEN_IMAGE_HOSTS = ['tenko.ua'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $brand = Brand::query()->where('slug', self::BRAND_SLUG)->first();
        if (! $brand) {
            $this->error('Бренд tenko не найден.');

            return self::FAILURE;
        }

        $items = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true, 512, JSON_THROW_ON_ERROR);
        $categories = Category::query()->whereIn('slug', array_unique(array_column($items, 'category_slug')))->pluck('id', 'slug');
        foreach (array_unique(array_column($items, 'category_slug')) as $slug) {
            if (! isset($categories[$slug])) {
                $this->error('Категория не найдена: '.$slug);

                return self::FAILURE;
            }
        }
        foreach ($items as $item) {
            foreach ($item['images'] as $image) {
                if (! is_file(public_path(ltrim($image, '/')))) {
                    $this->error('Нет изображения: '.$image);

                    return self::FAILURE;
                }
            }
        }

        $duplicateBrandIds = Brand::query()
            ->where('id', '!=', $brand->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) IN (?, ?)', ['tenko', 'тенко'])->orWhere('slug', 'like', 'tenko-%'))
            ->pluck('id');
        $unbranded = Product::query()->whereNull('brand_id')->where(fn ($q) => $q->where('name', 'like', '%Tenko%')->orWhere('name', 'like', '%TENKO%'));
        $keyer = new SyncThermokraftCommand;
        $tenkoProducts = Product::query()
            ->where(fn ($q) => $q->whereIn('brand_id', $duplicateBrandIds->push($brand->id))->orWhere('name', 'like', '%tenko%'))
            ->get(['id', 'name', 'slug', 'is_active', 'is_archived']);
        $existingKeys = [];
        foreach ($tenkoProducts as $product) {
            $existingKeys[$keyer->modelKey($product->name, 'Tenko')][] = $product;
        }
        $duplicateBrandIds = $duplicateBrandIds->reject(fn ($id) => (int) $id === (int) $brand->id)->values();

        $plan = [];
        foreach ($items as $item) {
            $slug = Str::slug($item['name']) ?: 'tenko-'.Str::random(6);
            $byName = Product::query()->where('name', $item['name'])->orWhere('slug', $slug)->first();
            $byModel = $item['kind'] === 'boiler' ? ($existingKeys[$keyer->modelKey($item['name'], 'Tenko')] ?? []) : [];
            $plan[] = ['item' => $item, 'slug' => $slug, 'existing' => $byName ?? ($byModel[0] ?? null)];
        }
        $broken = Product::query()->whereIn('brand_id', $duplicateBrandIds->concat([$brand->id]))->get(['id', 'name', 'images', 'category_id', 'brand_id'])
            ->filter(fn (Product $p) => collect($p->images ?: [])->contains(fn ($img) => collect(self::BROKEN_IMAGE_HOSTS)->contains(fn ($host) => str_contains((string) $img, $host))));

        $this->line('metric.tenko.planned_items='.count($items));
        $this->line('metric.tenko.to_create='.collect($plan)->whereNull('existing')->count());
        $this->line('metric.tenko.already_present='.collect($plan)->whereNotNull('existing')->count());
        $this->line('metric.tenko.duplicate_brands='.$duplicateBrandIds->count());
        $this->line('metric.tenko.products_on_duplicate_brands='.Product::query()->whereIn('brand_id', $duplicateBrandIds)->count());
        $this->line('metric.tenko.unbranded_tenko_products='.(clone $unbranded)->count());
        $this->line('metric.tenko.broken_external_images='.$broken->count());
        foreach (collect($plan)->whereNotNull('existing') as $row) {
            $this->line('present: '.$row['item']['name'].' -> '.$row['existing']->slug);
        }

        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

            return self::SUCCESS;
        }

        $stats = DB::transaction(function () use ($brand, $duplicateBrandIds, $unbranded, $plan, $categories, $broken): array {
            $stats = ['moved_to_brand' => 0, 'deactivated_duplicate_brands' => 0, 'created' => 0, 'images_repaired' => 0];
            if ($duplicateBrandIds->isNotEmpty()) {
                $stats['moved_to_brand'] += Product::query()->whereIn('brand_id', $duplicateBrandIds)->update(['brand_id' => $brand->id, 'updated_at' => now()]);
                foreach (DB::table('brand_supplier')->whereIn('brand_id', $duplicateBrandIds)->get() as $pivot) {
                    DB::table('brand_supplier')->updateOrInsert(['brand_id' => $brand->id, 'supplier_id' => $pivot->supplier_id], ['created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('brand_supplier')->whereIn('brand_id', $duplicateBrandIds)->delete();
                $stats['deactivated_duplicate_brands'] = Brand::query()->whereIn('id', $duplicateBrandIds)->update(['is_active' => false, 'updated_at' => now()]);
            }
            $stats['moved_to_brand'] += (clone $unbranded)->update(['brand_id' => $brand->id, 'updated_at' => now()]);

            foreach ($plan as $row) {
                if ($row['existing']) {
                    continue;
                }
                $this->createProduct($row['item'], $row['slug'], (int) $brand->id, (int) $categories[$row['item']['category_slug']]);
                $stats['created']++;
            }

            foreach ($broken as $product) {
                $donor = Product::query()
                    ->where('brand_id', $brand->id)->where('category_id', $product->category_id)->where('id', '!=', $product->id)
                    ->where('name', 'like', '%'.(preg_match('/гайка латунная ([0-9 ,\/]+")/u', $product->name, $m) ? $m[1] : 'ТЭН').'%')
                    ->get(['id', 'images'])
                    ->first(fn (Product $p) => collect($p->images ?: [])->contains(fn ($img) => str_starts_with((string) $img, '/img/')));
                if ($donor) {
                    $local = collect($donor->images)->first(fn ($img) => str_starts_with((string) $img, '/img/'));
                    $product->forceFill(['images' => [$local]])->save();
                    $stats['images_repaired']++;
                }
            }

            $brand->forceFill([
                'name' => 'Tenko',
                'h1' => 'Электрокотлы и ТЭНы Tenko',
                'logo' => 'img/brands/tenko.png',
                'country' => 'Украина',
                'producer' => 'TENKO',
                'content' => $this->brandContent(),
                'meta_title' => 'Электрокотлы Tenko — купить в %city%, цены',
                'meta_description' => 'Электрокотлы Tenko Эконом, Стандарт, Премиум, Премиум Плюс, Standart Digital и напольные НКЕ, ТЭНы и блоки ТЭНов. Цены от официального дистрибьютора, доставка по Беларуси.',
                'is_active' => true,
            ])->save();

            return $stats;
        });

        foreach ($stats as $key => $value) {
            $this->line("metric.tenko.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    private function createProduct(array $item, string $slug, int $brandId, int $categoryId): void
    {
        $specs = collect($item['specs'])->map(fn (array $s) => ['key' => $s[0], 'value' => $s[1]])->values()->all();
        $isBoiler = $item['kind'] === 'boiler';
        $metaName = $isBoiler
            ? 'Котел Tenko '.trim(preg_replace('/^Электрический котел Tenko |\s*\(.*$/u', '', $item['name'])).' — купить в %city%'
            : Str::limit($item['name'], 48, '').' — купить в %city%';

        Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $item['name'],
            'h1' => $item['name'],
            'slug' => $this->uniqueSlug($slug),
            'sku' => $this->uniqueSku($item),
            'price' => $item['price'],
            'price_old' => null,
            'currency' => 'BYN',
            'unit' => 'шт',
            'warranty' => $item['warranty'].' мес.',
            'short_description' => $item['short'],
            'content' => $this->content($item),
            'images' => $item['images'],
            'specs' => $specs,
            'service_info' => [
                ['key' => 'Гарантия', 'value' => $item['warranty'].' мес.'],
                ['key' => 'Производитель', 'value' => 'TENKO, Украина'],
                ['key' => 'Сервис в Беларуси', 'value' => 'Официальный дистрибьютор TENKO в Беларуси'],
            ],
            'content_source_url' => $item['source_url'],
            'meta_title' => $metaName,
            'meta_description' => Str::limit(strip_tags($item['short']).' Цена, наличие и доставка по Беларуси.', 175, ''),
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

        if ($item['kind'] === 'boiler') {
            $facts = array_filter([
                'Мощность' => $item['power'].' кВт',
                'Питание' => $item['voltage'] === '380' ? '380 В, трёхфазная сеть' : '220 В, однофазная сеть',
                'Объём теплообменника' => $item['heat_exchanger'] ? $item['heat_exchanger'].' л' : null,
                'Габариты (В×Ш×Г)' => $item['dimensions'] ? $item['dimensions'].' мм' : null,
                'Масса' => $item['weight'] ? 'до '.$item['weight'].' кг' : null,
                'Отапливаемая площадь (по данным производителя)' => $item['area'],
            ]);
            $html .= "<h3>Коротко о модели</h3>\n<ul>";
            foreach ($facts as $label => $value) {
                $html .= '<li><strong>'.$e($label).':</strong> '.$e((string) $value).'</li>';
            }
            $html .= "</ul>\n";
            $html .= "<h3>Подбор и подключение</h3>\n<p>Мощность электрокотла подбирают по теплопотерям здания, а не только по площади: на них влияют утепление стен и кровли, окна, высота потолков и регион. "
                .($item['voltage'] === '380'
                    ? 'Модель рассчитана на трёхфазную сеть 380 В — перед покупкой проверьте выделенную мощность по договору электроснабжения.'
                    : 'Модель работает от однофазной сети 220 В — проверьте, что выделенная мощность и проводка выдерживают нагрузку котла.')
                ." Подключение выполняет электрик: отдельный автоматический выключатель и сечение кабеля — по паспорту котла.</p>\n";
        } else {
            $html .= "<h3>Перед заказом</h3>\n<p>Сверьте мощность, напряжение, тип и размер присоединительной резьбы, а также монтажную длину со старым ТЭНом или паспортом оборудования. Если сомневаетесь — пришлите фото маркировки, подберём совместимую модель.</p>\n";
        }

        return $html.'<p>Гарантия производителя — '.$e($item['warranty']).' мес. Товар поставляется официальным дистрибьютором TENKO в Беларуси; цена и наличие обновляются автоматически.</p>';
    }

    private function brandContent(): string
    {
        return <<<'HTML'
<p><strong>Tenko</strong> — производитель электрических котлов, ТЭНов и электроконвекторов. В Беларуси оборудование поставляет официальный дистрибьютор, поэтому в каталоге KOTLOV.BY цены и наличие котлов и ТЭНов Tenko обновляются автоматически по его прайсу.</p>
<h2>Серии электрокотлов Tenko</h2>
<ul>
<li><strong>Эконом (КЕ)</strong> — простые настенные котлы без встроенного насоса, 3–30 кВт, ступенчатая регулировка мощности.</li>
<li><strong>Мини (КЕМ)</strong> — компактные котлы 3 и 4,5 кВт на 220 В для небольших помещений.</li>
<li><strong>Стандарт</strong> — со встроенным насосом Grundfos и группой безопасности, аналоговое управление.</li>
<li><strong>Standart Digital / Digital Plus</strong> — графитовый корпус, цифровое управление по воде и воздуху; версия Plus — со встроенным насосом Grundfos.</li>
<li><strong>Премиум</strong> — микропроцессорное управление, программатор, тихие твердотельные реле, насос Grundfos.</li>
<li><strong>Премиум Плюс</strong> — мини-котельная с насосом, расширительным баком 7 л и Wi‑Fi-управлением.</li>
<li><strong>Напольные НКЕ</strong> — промышленные котлы 30–135 кВт для больших домов, складов и цехов.</li>
</ul>
<h2>ТЭНы и комплектующие</h2>
<p>Для котлов Tenko в каталоге есть блоки ТЭНов с резьбой 2" и 1 1/4", регулируемые блок-ТЭНы БНР, медные ТЭНы RS для бойлеров косвенного нагрева и ТЭНы для полотенцесушителей.</p>
<h2>Как выбрать</h2>
<p>Мощность подбирают по теплопотерям дома. Модели на 220 В подходят для однофазной сети, мощные котлы — для трёхфазной сети 380 В. Если в системе уже есть насос и бак, достаточно серии Эконом или Standart Digital; для новой системы удобнее Стандарт, Премиум или Премиум Плюс со встроенной гидравликой. Поможем подобрать модель и рассчитать мощность.</p>
HTML;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uniqueSku(array $item): string
    {
        $base = 'TENKO-'.mb_strtoupper(substr(sha1($item['name']), 0, 10));
        $sku = $base;
        $i = 2;
        while (Product::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
