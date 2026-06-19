<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate product_attribute_values from products.specs JSON
 * for products imported from teplodvor (specs JSON, no attribute rows yet).
 *
 * Dry-run:  php artisan supplier:import-attributes-teplodvor --brand="Ariston"
 * Apply:    php artisan supplier:import-attributes-teplodvor --brand="Ariston" --apply
 */
class ImportAttributesTeplodvorCommand extends Command
{
    protected $signature = 'supplier:import-attributes-teplodvor
        {--brand=     : Brand name (required)}
        {--apply      : Write to DB (default: dry-run)}
        {--overwrite  : Replace existing attribute_values rows}';

    protected $description = 'Populate product_attribute_values from products.specs JSON (teplodvor imports)';

    // Spec keys to skip entirely — not useful as attributes
    private const SKIP_KEYS = [
        'Производитель', 'Производитель:', 'EAN', 'Страна',
    ];

    // Check-type attributes: value "да"/"нет"/"yes"/"no" → is_checked bool
    private const CHECK_KEYS = [
        'Теплоизоляция', 'Антибактериальная защита', 'Термостат безопасности',
        'Термостат', 'Дисплей', 'Удаленное управление', 'Сухой ТЭН',
        'Линия рециркуляции', 'Отображение температуры нагрева',
        'Защита от включения без воды', 'Защита от замерзания',
        'Регулировка температуры',
    ];

    public function handle(): int
    {
        $brandName = (string) $this->option('brand');
        $apply     = (bool)  $this->option('apply');
        $overwrite = (bool)  $this->option('overwrite');

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

        $this->line($apply ? '<fg=red;options=bold>APPLY MODE</>' : '<fg=yellow>DRY-RUN MODE</>');

        // Products with specs JSON, optionally filter those without attribute_values yet
        $query = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]');

        if (! $overwrite) {
            $withAttrs = DB::table('product_attribute_values')
                ->select('product_id')
                ->distinct()
                ->pluck('product_id')
                ->toArray();
            $query->whereNotIn('id', $withAttrs);
        }

        $products = $query->get(['id', 'name', 'category_id', 'specs']);
        $this->info(sprintf('Products to process: %d', $products->count()));

        $now   = now();
        $total = 0;

        foreach ($products as $product) {
            $specs = json_decode($product->specs, true);
            if (! is_array($specs) || empty($specs)) {
                continue;
            }

            $rows = 0;
            foreach ($specs as $spec) {
                $key   = trim((string) ($spec['key']   ?? ''));
                $val   = trim((string) ($spec['value'] ?? ''));
                $unit  = trim((string) ($spec['unit']  ?? ''));

                if ($key === '' || $val === '') continue;
                if (in_array($key, self::SKIP_KEYS, true)) continue;

                $fullVal = $val . ($unit !== '' ? ' ' . $unit : '');

                if (! $apply) {
                    $rows++;
                    continue;
                }

                $attrId = $this->ensureAttribute($key, (int) $product->category_id, $now);

                $isCheck = in_array($key, self::CHECK_KEYS, true);
                $checked = null;
                $stored  = $fullVal;

                if ($isCheck) {
                    $lower   = mb_strtolower($val);
                    $checked = in_array($lower, ['да', 'yes', '1', 'true'], true) ? 1 : 0;
                    $stored  = null;
                }

                DB::table('product_attribute_values')->updateOrInsert(
                    ['product_id' => $product->id, 'attribute_id' => $attrId],
                    [
                        'option_id'  => null,
                        'is_checked' => $checked,
                        'value'      => $stored,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $rows++;
            }

            $this->line(sprintf('  [id=%d] %s → %d attrs', $product->id, mb_substr($product->name, 0, 55), $rows));
            $total += $rows;
        }

        $this->info(sprintf('%s: %d attribute rows for %d products',
            $apply ? 'Written' : 'Would write',
            $total, $products->count()
        ));

        if (! $apply) {
            $this->line('Re-run with --apply to write changes.');
        }

        return self::SUCCESS;
    }

    private function ensureAttribute(string $name, int $categoryId, $now): int
    {
        $existing = DB::table('attributes')
            ->where('name', $name)
            ->where('category_id', $categoryId)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        // Try any category as fallback (attributes are often shared)
        $existing = DB::table('attributes')
            ->where('name', $name)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('attributes')->insertGetId([
            'category_id'    => $categoryId,
            'group_id'       => 0,
            'sort_order'     => 500,
            'type'           => in_array($name, self::CHECK_KEYS, true) ? 'check' : 'value',
            'name'           => $name,
            'suffix'         => null,
            'in_filter'      => false,
            'in_sort'        => false,
            'in_product'     => true,
            'in_brief'       => false,
            'is_comparable'  => true,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }
}
