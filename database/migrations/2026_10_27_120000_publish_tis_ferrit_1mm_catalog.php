<?php

use App\Console\Commands\LinkOneCByParametersCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The TiS «Феррит 1 мм» system (AISI 430, 1 mm) from teplov.ru, as Standard 30/50 were published.
 * Many of its parts already have cards (created from 1C or older): those keep their name, address
 * and price (aligned with sbg.by) and only get the system channel; the rest are created.
 */
return new class extends Migration
{
    private const DATA_FILE = 'data/catalog/tis-ferrit-1mm.json';

    private const SOURCE_URL = 'https://teplov.ru/catalog/dymokhodnye_sistemy/sistema_tis_ferrit_1_mm/';

    private const SKU_PREFIX = 'TISF1-';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $data = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true);
        if (! is_array($data['series'] ?? null)) {
            return;
        }

        $now = now();
        $supplierId = $this->supplierId($now);
        $brandId = (int) (DB::table('brands')->where('slug', 'teplov-i-suhov')->value('id')
            ?: DB::table('brands')->where('name', 'Теплов и Сухов')->value('id'));
        $categories = DB::table('categories')
            ->whereIn('slug', [
                'truby-mono', 'troyniki-mono', 'kolena-mono',
                'truby-sendvich', 'troyniki-sendvich', 'kolena-sendvich',
                'shibery-dymohod', 'kondensatootvody', 'krepleniya-dymohod',
                'zonty-deflektory', 'perehody-adaptery-dymohod', 'prochie-dymohod',
            ])
            ->pluck('id', 'slug');

        if (! $supplierId || ! $brandId || $categories->isEmpty()) {
            return;
        }

        $tisCards = DB::table('products')->where('is_archived', false)
            ->where(fn ($query) => $query->where('name', 'like', '%Теплов%')->orWhere('name', 'like', '%ТиС%'))
            ->get(['id', 'name']);
        $rate = $this->rubRate();
        foreach ($data['series'] as $seriesKey => $series) {
            foreach (($series['items'] ?? []) as $index => $item) {
                $this->upsertProduct((string) $seriesKey, (string) ($series['title'] ?? ''), (array) $item, $index,
                    (int) $supplierId, $brandId, $categories, $tisCards, $rate, $now);
            }
        }

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Published catalogue cards are intentionally durable.
    }

    private function upsertProduct(string $seriesKey, string $seriesTitle, array $item, int $index, int $supplierId, int $brandId,
        $categories, $tisCards, float $rate, $now): void
    {
        $sourceId = trim((string) ($item['source_id'] ?? ''));
        $sourceUrl = trim((string) ($item['source_url'] ?? ''));
        $sourceName = trim((string) ($item['name'] ?? ''));
        $rowName = trim((string) ($item['row_name'] ?? $sourceName));
        $diameter = trim((string) ($item['diameter'] ?? ''));
        $priceRub = (float) ($item['price_rub'] ?? 0);
        if ($sourceId === '' || $sourceName === '' || $diameter === '' || $priceRub <= 0) {
            return;
        }

        $sku = self::SKU_PREFIX.$sourceId;
        $execution = $seriesKey === 'thermo' ? 'Термо' : 'Моно';
        $name = 'Теплов и Сухов '.$sourceName;
        $categorySlug = $this->categorySlug($rowName, $execution);
        $categoryId = (int) ($categories[$categorySlug] ?? $categories['prochie-dymohod'] ?? 0);
        if (! $categoryId) {
            return;
        }
        $priceByn = round($priceRub * $rate, 2);
        $image = trim((string) ($item['source_image'] ?? ''));

        $productId = DB::table('products')->where('content_source_url', $sourceUrl)->where('is_archived', false)->value('id')
            ?: DB::table('supplier_products')->where('source_url', $sourceUrl)->whereNotNull('product_id')->value('product_id')
            ?: DB::table('products')->where('sku', $sku)->value('id')
            ?: $this->sameElement($sourceName, $tisCards);

        if ($productId) {
            // An existing card keeps its name, address and price; empty photo and source are filled.
            $card = DB::table('products')->where('id', $productId)->first(['images', 'content_source_url']);
            $updates = ['updated_at' => $now];
            if ($image !== '' && in_array(trim((string) $card->images), ['', '[]', 'null'], true)) {
                $updates['images'] = json_encode([$image], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (trim((string) $card->content_source_url) === '') {
                $updates['content_source_url'] = $sourceUrl;
            }
            DB::table('products')->where('id', $productId)->update($updates);
        } else {
            $specs = array_filter([
                'Серия' => 'ТиС «Феррит 1 мм»',
                'Исполнение' => $execution,
                'Тип элемента' => $rowName,
                'Диаметр' => $diameter,
                'Марка стали' => 'AISI 430',
                'Толщина стали' => $execution === 'Термо' ? '1,0 мм (внутренний контур)' : '1,0 мм',
                'Утеплитель' => str_contains(mb_strtolower($rowName), 'керамоволокно') ? 'Керамоволокно' : null,
                'Материал' => 'Нержавеющая сталь',
                'Страна производства' => 'Россия',
                'Срок поставки' => '5–7 рабочих дней',
            ], fn ($value): bool => $value !== null && $value !== '');
            $slug = 'tis-ferrit-'.trim(Str::slug(Str::after($sourceUrl, '/catalog/detail/')), '-');
            $productId = DB::table('products')->insertGetId([
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => null,
                'name' => $name,
                'h1' => $name,
                'sku' => $sku,
                'slug' => DB::table('products')->where('slug', $slug)->exists() ? $slug.'-'.$sourceId : $slug,
                'price' => $priceByn,
                'price_old' => null,
                'currency' => 'BYN',
                'short_description' => $rowName.'. Диаметр '.$diameter.' мм, нержавеющая сталь AISI 430. Поставка 5–7 рабочих дней.',
                'content' => $this->content($name, $diameter, trim((string) ($item['description'] ?? '')), $execution),
                'images' => json_encode($image !== '' ? [$image] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content_source_url' => $sourceUrl,
                'unit' => 'шт',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => false,
                'stock_qty' => null,
                'availability_status' => 'check',
                'requires_price_confirmation' => false,
                'manual_stock_management' => false,
                'is_featured' => false,
                'is_new' => true,
                'is_sale' => false,
                'sort_order' => ($seriesKey === 'thermo' ? 60000 : 50000) + ($index * 10),
                'meta_title' => $name.' купить в %city% — цена | KOTLOV',
                'meta_keywords' => 'ТиС Феррит 1 мм, '.$rowName.', дымоход '.$diameter.', AISI 430',
                'meta_description' => $rowName.' ТиС «Феррит 1 мм», диаметр '.$diameter.' мм. Цена в BYN, характеристики, фото и поставка по Беларуси за 5–7 рабочих дней.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $sku],
            [
                'product_id' => $productId,
                'product_sku' => DB::table('products')->where('id', $productId)->value('sku'),
                'supplier_article_normalized' => $sku,
                'supplier_name' => $sourceName,
                'source_url' => $sourceUrl,
                'source_wp_id' => $sourceId,
                // The maker's retail is not our cost: the channel is retail-only (cost unknown).
                'price' => null,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => null,
                'in_stock' => false,
                'stock_quantity' => null,
                'stock_status' => 'preorder',
                'stock_text' => 'Поставка под заказ: 5–7 рабочих дней',
                'warehouse_name' => 'СанБизнесГруп',
                'delivery_days' => 7,
                'match_status' => 'matched',
                'match_confidence' => 'manual_price_list',
                'raw' => json_encode([
                    'catalogue' => 'ТиС Феррит 1 мм',
                    'series' => $seriesTitle,
                    'element' => $rowName,
                    'diameter' => $diameter,
                    'official_retail_rub' => $priceRub,
                    'rub_to_byn_rate' => $rate,
                    'price_kind' => 'recommended_retail_with_vat',
                    'captured_at' => '2026-10-27',
                    'source' => self::SOURCE_URL,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /** The one TiS card of the same element and parameters, whatever its wording. */
    private function sameElement(string $sourceName, $tisCards): ?int
    {
        $signature = LinkOneCByParametersCommand::signature($sourceName);
        if ($signature === '' || substr_count($signature, '|') < 3) {
            return null;
        }
        $steels = fn (string $name): array => array_values(array_unique(preg_match_all('/(?<!\d)(304|310|316|321|409|430|439)(?!\d)/', $name, $m) ? $m[1] : []));
        $matches = $tisCards->filter(fn ($card) => LinkOneCByParametersCommand::signature((string) $card->name) === $signature
            && $steels((string) $card->name) == $steels($sourceName))->values();

        return $matches->count() === 1 ? (int) $matches[0]->id : null;
    }

    private function supplierId($now): ?int
    {
        $id = DB::table('suppliers')->where('code', 'sanbusinessgroup')->orWhere('name', 'ООО «СанБизнесГруп»')->value('id');
        if ($id) {
            DB::table('suppliers')->where('id', $id)->update(['is_active' => true, 'updated_at' => $now]);
        }

        return $id ? (int) $id : null;
    }

    private function rubRate(): float
    {
        $rate = (float) (DB::table('suppliers')
            ->where('currency', 'RUB')
            ->where(function ($query): void {
                $query->where('code', 'ecokamin')->orWhere('name', 'ООО «СанБизнесГруп»');
            })
            ->whereBetween('currency_rate', [0.02, 0.08])
            ->value('currency_rate') ?? 0);

        return $rate > 0 ? $rate : 0.0361;
    }

    private function categorySlug(string $name, string $execution): string
    {
        $lower = mb_strtolower($name);

        return match (true) {
            str_contains($lower, 'шибер') => 'shibery-dymohod',
            str_contains($lower, 'конденсат') => 'kondensatootvody',
            str_contains($lower, 'зонт') || str_contains($lower, 'дефлектор') => 'zonty-deflektory',
            str_contains($lower, 'адаптер') || str_contains($lower, 'переход') || str_contains($lower, 'конус') => 'perehody-adaptery-dymohod',
            str_contains($lower, 'тройник') => $execution === 'Моно' ? 'troyniki-mono' : 'troyniki-sendvich',
            str_contains($lower, 'отвод') => $execution === 'Моно' ? 'kolena-mono' : 'kolena-sendvich',
            str_contains($lower, 'труба') => $execution === 'Моно' ? 'truby-mono' : 'truby-sendvich',
            default => 'prochie-dymohod',
        };
    }

    private function content(string $name, string $diameter, string $description, string $execution): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeDescription = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $systemNote = $execution === 'Термо'
            ? 'Двустенная конструкция с керамоволоконной изоляцией для холодных участков дымохода и наружного монтажа.'
            : 'Одностенный элемент из стали толщиной 1 мм для участков дымохода внутри помещения и подключения к печи.';

        return <<<HTML
<p><strong>{$safeName}</strong> — элемент дымоходной системы ТиС «Феррит 1 мм» (AISI 430), типоразмер Ø{$diameter} мм. {$systemNote}</p>
<p>{$safeDescription}</p>
<h3>Как заказать</h3>
<p>Выберите нужный диаметр в переключателе карточки. Для комплектации всей трассы сообщите менеджеру модель печи, диаметр патрубка, высоту здания и расположение проходов.</p>
<p><strong>Срок поставки — 5–7 рабочих дней.</strong> Совместимость элементов и окончательную комплектацию менеджер проверит перед подтверждением заказа.</p>
HTML;
    }
};
