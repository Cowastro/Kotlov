<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate the structured «Характеристики» tab (product_attribute_values) for
 * Rusklimat products from their existing products.specs JSON, using a curated
 * per-category mapping to EXISTING attributes (no duplicates, no empties).
 *
 * Only writes a value when it is real; never overwrites an existing value;
 * unknown spec keys are skipped; the only new attribute created is one declared
 * explicitly in the map.
 *
 *   php artisan supplier:import-attributes-rusklimat --category=87 --limit=20 --dry-run
 *   php artisan supplier:import-attributes-rusklimat --category=87 --apply
 */
class ImportAttributesRusklimatCommand extends Command
{
    protected $signature = 'supplier:import-attributes-rusklimat
        {--category=  : Process one category id (default: all in the map)}
        {--limit=     : Max products to process}
        {--apply      : Write changes (default is preview)}
        {--dry-run    : Preview only (default)}';

    protected $description = 'Map existing products.specs to product_attribute_values (curated, per category).';

    private const SUPPLIER_CODE = 'rusklimat';

    /**
     * category_id => [ specs key => mapping ]
     *   mapping: ['attr_id' => int]                      — reuse existing attribute
     *         or ['create' => ['name','type','suffix']]  — create once (only with a value)
     */
    private const MAP = [
        // Радиаторы
        87 => [
            'Теплоотдача при Δt 70'             => ['attr_id' => 478],
            'Макс. тепловая мощность'           => ['attr_id' => 478],
            'Высота товара'                     => ['attr_id' => 484],
            'Ширина товара'                     => ['attr_id' => 474],
            'Глубина товара'                    => ['attr_id' => 475],
            'Вес товара (нетто)'                => ['attr_id' => 480],
            'Максимальное рабочее давление'     => ['attr_id' => 487],
            'Макс. температура теплоносителя'    => ['attr_id' => 486],
            'Объем воды в радиаторе'            => ['attr_id' => 477],
            'Резьба присоединения радиатора'    => ['attr_id' => 476],
            'Эффективен для помещ. площадью до' => ['create' => ['name' => 'Площадь обогрева', 'type' => 'value', 'suffix' => 'м²']],
        ],
        // Циркуляционные насосы
        60 => [
            'Макс. потребляемая мощность'       => ['attr_id' => 311],
            'Макс. вертикальный напор жидкости' => ['attr_id' => 312],
            'Гарантийный срок'                  => ['attr_id' => 328],
        ],
    ];

    /** @var array<int,object> attribute_id => attribute row (type/suffix/name) */
    private array $attrCache = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->line($apply
            ? '<fg=red;options=bold>APPLY: attribute values will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier not found.');
            return self::FAILURE;
        }

        $categories = $this->option('category')
            ? [(int) $this->option('category')]
            : array_keys(self::MAP);

        $stats = ['products' => 0, 'values_created' => 0, 'attrs_created' => 0,
                  'skipped_existing' => 0, 'skipped_no_value' => 0, 'junk_specs' => 0];

        foreach ($categories as $catId) {
            $map = self::MAP[$catId] ?? null;
            if (! $map) {
                $this->warn("No mapping for category {$catId} — skipped.");
                continue;
            }

            $q = DB::table('products as p')
                ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
                ->where('sp.supplier_id', $supplierId)
                ->where('p.category_id', $catId)
                ->where('p.is_archived', false)
                ->whereNotNull('p.specs')->where('p.specs', '!=', '')->where('p.specs', '!=', '[]')
                ->orderBy('p.id');
            if ($this->option('limit')) {
                $q->limit((int) $this->option('limit'));
            }
            $products = $q->get(['p.id', 'p.name', 'p.category_id', 'p.specs']);

            foreach ($products as $p) {
                $specs = json_decode($p->specs ?? '[]', true);
                if (! is_array($specs) || $specs === []) {
                    continue;
                }
                // Skip junk array-shaped specs (numeric keys, no names).
                if ($this->isNumericKeyed($specs)) {
                    $stats['junk_specs']++;
                    continue;
                }

                $stats['products']++;
                $printedHeader = false;

                foreach ($map as $specKey => $mapping) {
                    if (! array_key_exists($specKey, $specs)) {
                        continue;
                    }
                    $attr = $this->resolveAttribute($catId, $mapping, $apply, $stats);
                    if ($attr === null) {
                        continue;
                    }
                    $parsed = $this->parseValue((string) $specs[$specKey], $attr->type, (string) ($attr->suffix ?? ''));
                    if ($parsed === null) {
                        $stats['skipped_no_value']++;
                        continue;
                    }

                    // Never overwrite an existing value.
                    if ($attr->id && DB::table('product_attribute_values')
                            ->where('product_id', $p->id)->where('attribute_id', $attr->id)->exists()) {
                        $stats['skipped_existing']++;
                        continue;
                    }

                    if (! $printedHeader) {
                        $this->newLine();
                        $this->line(sprintf('<fg=cyan>id=%d</> %s', $p->id, mb_substr((string) $p->name, 0, 56)));
                        $printedHeader = true;
                    }

                    $display = $attr->type === 'check'
                        ? ($parsed === '1' ? 'Да' : 'Нет')
                        : $parsed . ($attr->suffix ? ' ' . $attr->suffix : '');
                    $this->line(sprintf('    %s = %s', $attr->name, $display));

                    if ($apply && $attr->id) {
                        DB::table('product_attribute_values')->insert([
                            'product_id'   => $p->id,
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
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));

        return self::SUCCESS;
    }

    /** Resolve mapping to an attribute row (existing or created). Null if unusable. */
    private function resolveAttribute(int $catId, array $mapping, bool $apply, array &$stats): ?object
    {
        if (isset($mapping['attr_id'])) {
            $id = (int) $mapping['attr_id'];
            if (! isset($this->attrCache[$id])) {
                $this->attrCache[$id] = DB::table('attributes')->where('id', $id)->first(['id', 'name', 'type', 'suffix']);
            }
            return $this->attrCache[$id] ?: null;
        }

        if (isset($mapping['create'])) {
            $def = $mapping['create'];
            $existing = DB::table('attributes')
                ->where('category_id', $catId)->where('name', $def['name'])
                ->first(['id', 'name', 'type', 'suffix']);
            if ($existing) {
                return $existing;
            }
            if (! $apply) {
                // Pseudo row for preview (no id → won't write).
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
            $this->line("  <fg=green>+ создан атрибут «{$def['name']}» ({$def['type']}, {$def['suffix']})</>");
            return (object) ['id' => $id, 'name' => $def['name'], 'type' => $def['type'], 'suffix' => $def['suffix'] ?? ''];
        }

        return null;
    }

    /** Parse a raw spec value for an attribute. Returns clean value, '1'/'0' for check, or null. */
    private function parseValue(string $raw, string $type, string $suffix): ?string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');
        if ($raw === '' || $raw === '—') {
            return null;
        }

        if ($type === 'check') {
            $low = mb_strtolower($raw);
            if (in_array($low, ['да', 'yes', 'есть', '+', 'true', '1'], true)) {
                return '1';
            }
            if (in_array($low, ['нет', 'no', '-', 'false', '0'], true)) {
                return '0';
            }
            return null;
        }

        // value: drop the unit suffix if the supplier repeated it, keep the rest.
        if ($suffix !== '') {
            $raw = trim(preg_replace('/\s*' . preg_quote($suffix, '/') . '\.?$/ui', '', $raw) ?? $raw);
        }
        $raw = trim($raw, " \t\u{00A0}:;");

        // Reject empty / pure-unit leftovers (orphan units).
        if ($raw === '' || ! preg_match('/[0-9A-Za-zА-Яа-яё]/u', $raw)) {
            return null;
        }
        return mb_substr($raw, 0, 120);
    }

    private function isNumericKeyed(array $specs): bool
    {
        foreach (array_keys($specs) as $k) {
            if (! is_int($k) && ! ctype_digit((string) $k)) {
                return false;
            }
        }
        return true;
    }
}
