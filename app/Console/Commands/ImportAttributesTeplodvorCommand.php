<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate product_attribute_values from products.specs JSON
 * for products imported from teplodvor (specs filled, attribute_values empty).
 *
 * Dry-run:  php artisan supplier:import-attributes-teplodvor --brand="Ariston"
 * Apply:    php artisan supplier:import-attributes-teplodvor --brand="Ariston" --apply
 */
class ImportAttributesTeplodvorCommand extends Command
{
    protected $signature = 'supplier:import-attributes-teplodvor
        {--brand=    : Brand name (required)}
        {--apply     : Write to DB (default: dry-run)}
        {--limit=    : Max products to process}';

    protected $description = 'Populate product_attribute_values from products.specs JSON (teplodvor imports)';

    /**
     * Curated mapping: category_id → teplodvor spec key → attribute config.
     *
     * 'attr_id' — use existing attribute row with this id.
     * 'raw'     — store full text value without digit extraction (for text-only fields).
     *
     * Only value/check types are mapped here.
     * select types require option_id lookup — excluded intentionally.
     */
    private const MAP = [
        // ── Электрические водонагреватели ─────────────────────────────────────────
        98 => [
            'Объем'                                 => ['attr_id' => 493],        // value л
            'Максимальная температура нагрева воды' => ['attr_id' => 499],        // value °C
            'Максимальное давление воды'            => ['attr_id' => 505],        // value бар
            'Дисплей'                               => ['attr_id' => 506],        // check
            'Термостат безопасности'                => ['attr_id' => 509],        // check
            'Вес'                                   => ['attr_id' => 512],        // value кг
            'Гарантия на внутренний бак'            => ['attr_id' => 513],        // value (text)
            'Гарантия на водонагреватель'           => ['attr_id' => 514],        // value (text)
            'Теплоизоляция'                         => ['attr_id' => 517],        // check
            'Материал теплоизоляции'                => ['attr_id' => 518, 'raw' => true],  // value (text, no digits)
            'Антибактериальная защита'              => ['attr_id' => 587],        // check
            'Высота'                                => ['attr_id' => 563],        // value мм
            'Ширина'                                => ['attr_id' => 564],        // value мм
            'Глубина'                               => ['attr_id' => 565],        // value мм
            'Покрытие внутреннего бака'             => ['attr_id' => 503, 'raw' => true],  // value (text, no digits)
        ],

        // ── Газовые котлы ─────────────────────────────────────────────────────────
        53 => [
            'Площадь обогрева'                      => ['attr_id' => 90],         // value кв.м
            'Мощность'                              => ['attr_id' => 92],         // value кВт
            'Объем расширительного бака'            => ['attr_id' => 93],         // value л
            'Расширительный бак'                    => ['attr_id' => 93],         // value л (alt key)
            'Производительность ГВС'                => ['attr_id' => 97],         // value л/мин
            'КПД'                                   => ['attr_id' => 101],        // value %
            'Расход газа (природный/сжиженный)'     => ['attr_id' => 198],        // value куб.м/час
            'Диаметр дымохода (коаксиальный/раздельный)' => ['attr_id' => 224, 'raw' => true],
            'Потребление электроэнергии'             => ['attr_id' => 261],        // value Вт
            'Вес'                                   => ['attr_id' => 110],        // value кг
            'КПД в режиме 75/60°С'                 => ['attr_id' => 101],        // value %
        ],

        // ── Газовые колонки ───────────────────────────────────────────────────────
        298 => [
            'Производительность'                    => ['attr_id' => 97],         // value л/мин (reuse)
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Коаксиальные дымоходы/фитинги ────────────────────────────────────────
        57 => [
            'Длина'                                 => ['attr_id' => 378],        // value мм
            'Вес'                                   => ['attr_id' => 910],        // value г
        ],

        // ── Термостаты/автоматика ─────────────────────────────────────────────────
        58 => [
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Прочие аксессуары (fallback) ─────────────────────────────────────────
        195 => [
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],
    ];

    /** @var array<int,object> */
    private array $attrCache = [];

    public function handle(): int
    {
        $brandName = (string) $this->option('brand');
        $apply     = (bool)  $this->option('apply');

        if (! $brandName) {
            $this->error('--brand is required');
            return self::FAILURE;
        }

        $brand = DB::table('brands')
            ->where('name', $brandName)
            ->orWhere('name', 'like', $brandName . '%')
            ->first();

        if (! $brand) {
            $this->error("Brand not found: {$brandName}");
            return self::FAILURE;
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: attribute values will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        $q = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]')
            ->whereNotExists(fn ($w) => $w
                ->from('product_attribute_values')
                ->whereColumn('product_attribute_values.product_id', 'products.id'))
            ->orderBy('id');

        if ($this->option('limit')) {
            $q->limit((int) $this->option('limit'));
        }

        $products = $q->get(['id', 'name', 'category_id', 'specs']);
        $this->info(sprintf('Products to process: %d', $products->count()));

        $stats = ['products' => 0, 'values_created' => 0, 'attrs_created' => 0,
                  'skipped_no_map' => 0, 'skipped_no_value' => 0];

        foreach ($products as $product) {
            $specs = json_decode($product->specs, true);
            if (! is_array($specs) || $specs === []) {
                continue;
            }

            // Convert [{key,value,unit}] array → flat dict key => 'value unit'
            $data = [];
            foreach ($specs as $row) {
                $key = trim((string) ($row['key'] ?? ''));
                $val = trim((string) ($row['value'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                if ($key !== '' && $val !== '') {
                    $data[$key] = $val . ($unit !== '' ? ' ' . $unit : '');
                }
            }

            $catId = (int) $product->category_id;
            $map   = self::MAP[$catId] ?? null;

            if (! $map) {
                $stats['skipped_no_map']++;
                $this->line(sprintf('  <fg=yellow>NO MAP</> cat=%d [id=%d] %s', $catId, $product->id, mb_substr($product->name, 0, 50)));
                continue;
            }

            $stats['products']++;
            $printedHeader = false;

            foreach ($map as $specKey => $mapping) {
                if (! array_key_exists($specKey, $data)) {
                    continue;
                }

                $attr = $this->resolveAttribute($catId, $mapping, $apply, $stats);
                if ($attr === null) {
                    continue;
                }

                $raw = (string) $data[$specKey];
                $useRaw = ! empty($mapping['raw']);
                $parsed = $useRaw ? ($raw !== '' ? $raw : null) : $this->parseValue($raw, $attr->type, (string) ($attr->suffix ?? ''));

                if ($parsed === null) {
                    $stats['skipped_no_value']++;
                    continue;
                }

                if (! $printedHeader) {
                    $this->newLine();
                    $this->line(sprintf('<fg=cyan>id=%d</> %s', $product->id, mb_substr($product->name, 0, 56)));
                    $printedHeader = true;
                }

                $display = $attr->type === 'check'
                    ? ($parsed === '1' ? 'Да' : 'Нет')
                    : $parsed . ($attr->suffix ? ' ' . $attr->suffix : '');
                $this->line(sprintf('    %s = %s', $attr->name, $display));

                if ($apply && $attr->id) {
                    DB::table('product_attribute_values')->insert([
                        'product_id'   => $product->id,
                        'attribute_id' => $attr->id,
                        'option_id'    => null,
                        'is_checked'   => $attr->type === 'check' ? ($parsed === '1' ? 1 : 0) : null,
                        'value'        => $attr->type === 'check' ? null : $parsed,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
                $stats['values_created']++;
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));

        if (! $apply) {
            $this->line('Re-run with --apply to write changes.');
        }

        return self::SUCCESS;
    }

    private function resolveAttribute(int $catId, array $mapping, bool $apply, array &$stats): ?object
    {
        if (isset($mapping['attr_id'])) {
            $id = (int) $mapping['attr_id'];
            $this->attrCache[$id] ??= DB::table('attributes')->where('id', $id)->first(['id', 'name', 'type', 'suffix']);
            return $this->attrCache[$id] ?: null;
        }

        if (isset($mapping['create'])) {
            $def = $mapping['create'];
            $existing = DB::table('attributes')
                ->where('category_id', $catId)
                ->where('name', $def['name'])
                ->first(['id', 'name', 'type', 'suffix']);
            if ($existing) {
                return $existing;
            }
            if (! $apply) {
                return (object) ['id' => null, 'name' => $def['name'], 'type' => $def['type'], 'suffix' => $def['suffix'] ?? ''];
            }
            $sort = (int) DB::table('attributes')->where('category_id', $catId)->max('sort_order') + 1;
            $id = DB::table('attributes')->insertGetId([
                'category_id'   => $catId,
                'type'          => $def['type'],
                'name'          => $def['name'],
                'suffix'        => $def['suffix'] ?? null,
                'in_product'    => true,
                'in_filter'     => false,
                'in_brief'      => false,
                'in_sort'       => false,
                'is_comparable' => false,
                'sort_order'    => $sort,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $stats['attrs_created']++;
            $this->line("  <fg=green>+ атрибут «{$def['name']}» создан</>");
            return (object) ['id' => $id, 'name' => $def['name'], 'type' => $def['type'], 'suffix' => $def['suffix'] ?? ''];
        }

        return null;
    }

    private function parseValue(string $raw, string $type, string $suffix): ?string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');
        if ($raw === '' || $raw === '—') {
            return null;
        }
        if ($type === 'check') {
            $low = mb_strtolower($raw);
            if (in_array($low, ['да', 'yes', 'есть', '+', 'true', '1'], true)) return '1';
            if (in_array($low, ['нет', 'no', '-', 'false', '0'], true)) return '0';
            return null;
        }
        if ($suffix !== '') {
            if (preg_match('/-?\d+(?:[.,]\d+)?/u', $raw, $m)) {
                return str_replace(',', '.', $m[0]);
            }
            return null;
        }
        if (! preg_match('/\d/u', $raw)) {
            return null;
        }
        return mb_substr(trim($raw, " \t\u{00A0}:;"), 0, 60);
    }
}
