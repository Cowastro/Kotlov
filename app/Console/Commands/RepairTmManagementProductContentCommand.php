<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairTmManagementProductContentCommand extends Command
{
    protected $signature = 'supplier:repair-tm-content
        {--apply : Write changes to the database}
        {--all : Refresh all linked TM Management products, not only obviously broken cards}
        {--limit= : Limit products}
        {--sku= : Repair one product SKU}';

    protected $description = 'Repair public SEO content and specs format for TM Management linked products without exposing supplier name.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $all = (bool) $this->option('all');
        $sku = trim((string) $this->option('sku'));
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('s.code', 'tm-management')
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.content',
                'p.short_description',
                'p.meta_title',
                'p.meta_keywords',
                'p.meta_description',
                'p.specs',
                'b.name as brand_name',
                'c.name as category_name',
            ])
            ->orderBy('p.id');

        if ($sku !== '') {
            $query->where('p.sku', $sku);
        }

        if (! $all) {
            $query->where(function ($q) {
                $q->whereNull('p.content')
                    ->orWhere('p.content', '')
                    ->orWhere('p.content', 'like', '%ТМ Менеджмент%')
                    ->orWhere('p.content', 'like', '%поставщик%')
                    ->orWhere('p.content', 'like', '%поставщика%')
                    ->orWhere('p.short_description', 'like', '%ТМ Менеджмент%')
                    ->orWhere('p.short_description', 'like', '%%city%%')
                    ->orWhere('p.meta_keywords', 'like', '%ТМ Менеджмент%');
            });
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->get()->unique('id')->values();
        $examples = [];
        $changed = 0;

        foreach ($rows as $product) {
            $brand = trim((string) ($product->brand_name ?: ''));
            $category = trim((string) ($product->category_name ?: ''));
            $specs = $this->normalizeSpecs($product->specs, (string) $product->name, $category);

            $updates = [
                'content' => $this->content((string) $product->name, $brand, $category, $specs),
                'short_description' => $this->shortDescription((string) $product->name, $brand, $category),
                'meta_title' => ((string) $product->name) . ' купить в %city%',
                'meta_keywords' => $this->metaKeywords((string) $product->name, $brand, $category),
                'meta_description' => $this->metaDescription((string) $product->name, $brand, $category),
                'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($apply) {
                DB::table('products')->where('id', $product->id)->update($updates);
            }

            $changed++;

            if (count($examples) < 12) {
                $examples[] = [
                    'sku' => $product->sku,
                    'brand' => $brand ?: '—',
                    'specs' => count($specs),
                    'name' => Str::limit((string) $product->name, 55),
                ];
            }
        }

        $this->table(['sku', 'brand', 'specs', 'name'], $examples);
        $this->table(['metric', 'count'], [
            ['mode', $apply ? 'apply' : 'dry-run'],
            ['products', $rows->count()],
            ['changed', $changed],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{key:string,value:string,unit:string}>
     */
    private function normalizeSpecs(mixed $raw, string $productName = '', string $category = ''): array
    {
        $decoded = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $specs = [];

        foreach ($decoded as $key => $value) {
            if (is_array($value)) {
                $specKey = $value['key'] ?? $value['name'] ?? $value['title'] ?? (is_string($key) ? $key : '');
                $specValue = $value['value'] ?? $value['val'] ?? null;
                $unit = $value['unit'] ?? '';
            } else {
                $specKey = is_string($key) ? $key : '';
                $specValue = $value;
                $unit = '';
            }

            $specKey = $this->cleanText((string) $specKey);
            $specValue = is_scalar($specValue) ? $this->cleanText((string) $specValue) : '';
            $unit = is_scalar($unit) ? $this->cleanText((string) $unit) : '';
            $specValue = $this->fixSpecValue($specKey, $specValue);

            if ($specKey === '' || $specValue === '') {
                continue;
            }

            if ($this->isJunkSpec($specKey, $specValue) || $this->isIncompatibleSpec($specKey, $productName, $category)) {
                continue;
            }

            $specs[mb_strtolower($specKey)] = [
                'key' => $specKey,
                'value' => $specValue,
                'unit' => $unit,
            ];
        }

        return array_values($specs);
    }

    /**
     * @param array<int,array{key:string,value:string,unit:string}> $specs
     */
    private function content(string $name, string $brand, string $category, array $specs): string
    {
        $safeName = e($name);
        $safeBrand = e($brand !== '' ? $brand : 'проверенного бренда');
        $safeCategory = e($category !== '' ? $category : 'каталога отопления и водоснабжения');
        $lead = $this->categoryLead($category);
        $usage = $this->usageText($category);
        $benefit = $this->benefitText($category);
        $specList = $this->specListHtml($specs);

        $specBlock = $specList !== ''
            ? <<<HTML
<h3>Ключевые параметры</h3>
<ul>
{$specList}
</ul>
HTML
            : '';

        return <<<HTML
<p><strong>{$safeName}</strong> — позиция бренда {$safeBrand} из раздела «{$safeCategory}». Товар подбирают для частных домов, квартир, котельных, систем водоснабжения, канализации и инженерных узлов, где важны совместимость, надёжность и понятная комплектация.</p>

<p>{$lead} {$usage} Если вы выбираете {$safeName} в %city%, специалисты KOTLOV.BY помогут сверить параметры, подобрать совместимые элементы и уточнить срок поставки по Беларуси.</p>

<p>{$benefit} Перед заказом важно проверить размеры, подключение, рабочие режимы и требования к монтажу. Это снижает риск ошибок, лишних переделок и покупки неподходящих комплектующих.</p>

{$specBlock}

<h3>Что уточнить перед заказом</h3>
<ul>
    <li>подходит ли товар к вашему оборудованию и условиям эксплуатации;</li>
    <li>актуальную комплектацию, наличие и срок поставки в %city%;</li>
    <li>нужны ли переходники, крепёж, автоматика или расходные материалы;</li>
    <li>возможность консультации, монтажа и обслуживания по Беларуси.</li>
</ul>
HTML;
    }

    private function shortDescription(string $name, string $brand, string $category): string
    {
        $subject = $brand !== '' ? $brand : $name;
        $categoryPart = $category !== '' ? ' для раздела «' . $category . '»' : '';

        return Str::limit($subject . $categoryPart . ' — подбор, консультация и поставка по Беларуси. Уточняйте наличие, комплектацию и срок поставки.', 240, '');
    }

    private function metaKeywords(string $name, string $brand, string $category): string
    {
        return collect([$name, $brand, $category, 'купить в %city%', 'KOTLOV.BY'])
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function metaDescription(string $name, string $brand, string $category): string
    {
        $prefix = $brand !== '' ? $brand . ' ' : '';
        $tail = $category !== '' ? ' из раздела «' . $category . '»' : '';

        return Str::limit($prefix . $name . $tail . ': характеристики, подбор, консультация и поставка в %city% по Беларуси.', 245, '');
    }

    private function categoryLead(string $category): string
    {
        $text = mb_strtolower($category);

        return match (true) {
            str_contains($text, 'насос') => 'Для насосного оборудования особенно важны производительность, напор, тип подключения и условия работы.',
            str_contains($text, 'бак') => 'Для баков и гидроаккумуляторов особенно важны объём, давление, тип мембраны и совместимость с системой.',
            str_contains($text, 'комплект') => 'Для комплектующих особенно важны точные размеры, резьбы, материалы и совместимость с основным оборудованием.',
            str_contains($text, 'кот') => 'Для отопительного оборудования особенно важны мощность, автоматика, безопасность и требования к монтажу.',
            default => 'Для инженерного оборудования особенно важны корректные характеристики, совместимость и качество монтажа.',
        };
    }

    private function usageText(string $category): string
    {
        $text = mb_strtolower($category);

        return match (true) {
            str_contains($text, 'фекаль') => 'Фекальные насосы применяют для отвода загрязнённой воды и стоков, поэтому здесь особенно важны пропускная способность, допустимый размер частиц и защита двигателя.',
            str_contains($text, 'дренаж') => 'Дренажные насосы используют для откачки воды из приямков, подвалов, колодцев и технических помещений, где нужны стабильный расход и достаточный напор.',
            str_contains($text, 'насос') => 'Насосное оборудование выбирают под конкретную задачу: водоснабжение, повышение давления, циркуляцию, отвод конденсата или перекачку воды.',
            str_contains($text, 'бак') || str_contains($text, 'мембран') => 'Мембранные баки и гидроаккумуляторы помогают стабилизировать давление, снизить частоту включения насоса и продлить ресурс системы.',
            str_contains($text, 'комплект') => 'Комплектующие должны точно подходить по размеру и назначению: даже небольшая ошибка в резьбе, диаметре или типе крепления может усложнить монтаж.',
            str_contains($text, 'кот') => 'Оборудование для отопления подбирают с учётом мощности, топлива, дымохода, автоматики и требований к безопасности котельной.',
            default => 'Такие позиции подбирают не только по цене, но и по назначению, материалам, подключению и условиям эксплуатации.',
        };
    }

    private function benefitText(string $category): string
    {
        $text = mb_strtolower($category);

        return match (true) {
            str_contains($text, 'насос') => 'Правильно подобранный насос работает тише, реже перегружается и лучше держит нужный режим в системе.',
            str_contains($text, 'бак') || str_contains($text, 'мембран') => 'Корректный объём бака помогает защитить насос от частых запусков и сделать давление воды более стабильным.',
            str_contains($text, 'комплект') => 'Правильная комплектующая экономит время на монтаже и помогает собрать узел без временных решений.',
            str_contains($text, 'кот') => 'Грамотный подбор отопительного оборудования влияет на расход топлива, комфорт зимой и безопасность эксплуатации.',
            default => 'Грамотный подбор инженерной позиции помогает системе работать предсказуемо и без лишних затрат на переделку.',
        };
    }

    /**
     * @param array<int,array{key:string,value:string,unit:string}> $specs
     */
    private function specListHtml(array $specs): string
    {
        $preferred = [
            'мощность',
            'производительность',
            'максимальный расход',
            'максимальный напор',
            'объем',
            'объём',
            'давление',
            'диаметр',
            'присоединительный размер',
            'материал',
            'вес',
            'габариты',
        ];

        $ranked = collect($specs)
            ->map(function (array $spec) use ($preferred) {
                $key = (string) ($spec['key'] ?? '');
                $lower = mb_strtolower($key);
                $rank = 100;

                foreach ($preferred as $index => $needle) {
                    if (str_contains($lower, $needle)) {
                        $rank = $index;
                        break;
                    }
                }

                return ['rank' => $rank, 'spec' => $spec];
            })
            ->sortBy('rank')
            ->take(6)
            ->pluck('spec')
            ->values();

        return $ranked
            ->map(function (array $spec) {
                $key = e((string) ($spec['key'] ?? ''));
                $value = e(trim((string) ($spec['value'] ?? '') . ' ' . (string) ($spec['unit'] ?? '')));

                return $key !== '' && $value !== ''
                    ? "    <li><strong>{$key}:</strong> {$value}</li>"
                    : null;
            })
            ->filter()
            ->implode("\n");
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);
    }

    private function fixSpecValue(string $key, string $value): string
    {
        $keyLower = mb_strtolower($key);

        if (str_contains($keyLower, 'мощность двигателя')
            && preg_match('/^(\d+(?:[,.]\d+)?)\s*кВт$/iu', $value, $matches) === 1
            && (float) str_replace(',', '.', $matches[1]) < 1000
        ) {
            return $matches[1] . ' Вт';
        }

        return $value;
    }

    private function isJunkSpec(string $key, string $value): bool
    {
        $normalizedKey = $this->normalizeSpecToken($key);
        $normalizedValue = $this->normalizeSpecToken($value);

        if (preg_match('/^(поставщик|цена опт|цена ррц|артикул поставщика|розничная цена|закупка)$/iu', $key) === 1) {
            return true;
        }

        if (
            in_array($normalizedKey, ['характеристика', 'параметр', 'показатель', 'название', 'наименование'], true)
            && in_array($normalizedValue, ['значение', 'характеристика', 'параметр'], true)
        ) {
            return true;
        }

        if (preg_match('/^(?:доставка|оплата|наличие|отзывы|описание|купить|корзина|похожие товары)$/iu', $normalizedKey) === 1) {
            return true;
        }

        $keyLen = mb_strlen($key);
        $valueLen = mb_strlen($value);
        $looksLikeSentence = preg_match('/[.!?]|(?:\s(?:и|или|для|при|может|позволяет|обеспечивает|устанавливается|который|которая|которые|поверхность)\s)/iu', $key) === 1;

        return $keyLen > 65 && ($valueLen > 18 || $looksLikeSentence);
    }

    private function isIncompatibleSpec(string $key, string $productName, string $category): bool
    {
        $key = $this->normalizeSpecToken($key);
        $subject = $this->normalizeSpecToken($productName . ' ' . $category);

        $isTank = preg_match('/(?:гидроаккумулятор|гидро аккумулятор|бак|мембран)/u', $subject) === 1;
        if (! $isTank && preg_match('/(?:гидроаккумулятор|гидро аккумулятор|мембран|фланц)/u', $key) === 1) {
            return true;
        }

        $isBoilerOrHeater = preg_match('/(?:котел|котёл|печь|камин|горелк|водонагрев)/u', $subject) === 1;
        if (! $isBoilerOrHeater && preg_match('/(?:дымоход|топлив|камера сгорания|теплообменник)/u', $key) === 1) {
            return true;
        }

        return false;
    }

    private function normalizeSpecToken(string $value): string
    {
        $value = mb_strtolower($this->cleanText($value));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
