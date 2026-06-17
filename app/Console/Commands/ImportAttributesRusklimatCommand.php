<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScrapesRusklimatSpecs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Populate the structured «Характеристики» tab (product_attribute_values) for
 * Rusklimat products, via a curated per-category mapping.
 *
 * Two data sources:
 *   --source=scrape : scrape rusklimat.ru/product (reliable; default for Климат)
 *   --source=specs  : read existing products.specs JSON (only where it is clean)
 *
 * Only writes real values; never overwrites; unknown keys skipped; numeric
 * (unit) attributes store just the number (no orphan units); creates only the
 * attributes declared in the map.
 *
 *   php artisan supplier:import-attributes-rusklimat --category=304 --source=scrape --limit=10 --dry-run
 *   php artisan supplier:import-attributes-rusklimat --category=304 --source=scrape --apply
 */
class ImportAttributesRusklimatCommand extends Command
{
    use ScrapesRusklimatSpecs;

    protected $signature = 'supplier:import-attributes-rusklimat
        {--category=     : Category id (required for scrape; default all mapped for specs)}
        {--source=specs  : specs | scrape}
        {--limit=        : Max products to process}
        {--sleep=500     : Delay between products in ms (scrape)}
        {--apply         : Write changes (default is preview)}
        {--dry-run       : Preview only (default)}';

    protected $description = 'Map product specs to product_attribute_values (curated, per category).';

    private const SUPPLIER_CODE = 'rusklimat';

    /** Re-try a product that yielded no specs only after this many days. */
    private const FAILURE_TTL_DAYS = 30;

    /** Mapping for the products.specs JSON source: specs key => attribute. */
    private const MAP_SPECS = [
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
        60 => [
            'Макс. потребляемая мощность'       => ['attr_id' => 311],
            'Макс. вертикальный напор жидкости' => ['attr_id' => 312],
            'Гарантийный срок'                  => ['attr_id' => 328],
        ],
    ];

    /** Mapping for the scrape source: rusklimat.ru spec key => attribute. */
    private const MAP_SCRAPE = [
        // Климат (категория без атрибутов — создаём чистый набор)
        304 => [
            'Охлаждение'                 => ['create' => ['name' => 'Мощность охлаждения', 'type' => 'value', 'suffix' => 'кВт']],
            'Обогрев'                    => ['create' => ['name' => 'Мощность обогрева', 'type' => 'value', 'suffix' => 'кВт']],
            'Уровень шума внутр. блока'  => ['create' => ['name' => 'Уровень шума внутреннего блока', 'type' => 'value', 'suffix' => 'дБ']],
            'Площадь, м²'                => ['create' => ['name' => 'Обслуживаемая площадь', 'type' => 'value', 'suffix' => 'м²']],
            'Инверторная технология'     => ['create' => ['name' => 'Инверторная технология', 'type' => 'check', 'suffix' => '']],
            'Работа с умным домом'       => ['create' => ['name' => 'Управление Wi-Fi / умный дом', 'type' => 'check', 'suffix' => '']],
            'Гарантийный срок'           => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => '']],
        ],
    ];

    /** @var array<int,object> attribute_id => row */
    private array $attrCache = [];

    public function handle(): int
    {
        $apply  = (bool) $this->option('apply') && ! $this->option('dry-run');
        $source = (string) $this->option('source');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: attribute values will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier not found.');
            return self::FAILURE;
        }

        return $source === 'scrape'
            ? $this->runScrape((int) $supplierId, $apply)
            : $this->runSpecs((int) $supplierId, $apply);
    }

    // ── Source: scrape rusklimat.ru/product ───────────────────────────────────────

    private function runScrape(int $supplierId, bool $apply): int
    {
        $catId = (int) $this->option('category');
        $map   = self::MAP_SCRAPE[$catId] ?? null;
        if (! $map) {
            $this->error("No scrape mapping for category {$catId}. Mapped: " . implode(', ', array_keys(self::MAP_SCRAPE)));
            return self::FAILURE;
        }
        if (! env('SERPER_API_KEY')) {
            $this->error('SERPER_API_KEY not set.');
            return self::FAILURE;
        }

        $q = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.category_id', $catId)
            ->where('p.is_archived', false)
            ->whereNotExists(fn ($w) => $w->from('product_attribute_values as pav')->whereColumn('pav.product_id', 'p.id'))
            ->when($this->failuresTable(), fn ($qq) => $qq->whereNotExists(fn ($w) => $w
                ->from('attribute_import_failures as f')
                ->whereColumn('f.product_id', 'p.id')
                ->where('f.attempted_at', '>=', now()->subDays(self::FAILURE_TTL_DAYS))))
            ->orderBy('p.id');
        if ($this->option('limit')) {
            $q->limit((int) $this->option('limit'));
        }
        $products = $q->get(['p.id', 'p.name', 'p.category_id', 'b.name as brand', 'sp.supplier_article']);

        $this->info(sprintf('Scrape source · category %d · %d products to process', $catId, $products->count()));

        $stats = ['products' => 0, 'page_found' => 0, 'not_found' => 0,
                  'values_created' => 0, 'attrs_created' => 0, 'skipped_no_value' => 0];

        foreach ($products as $p) {
            $stats['products']++;
            $this->newLine();
            $this->line(sprintf('<fg=cyan>id=%d</> %s', $p->id, mb_substr((string) $p->name, 0, 56)));

            $url = $this->findProductPage((string) ($p->supplier_article ?? ''), (string) ($p->brand ?? ''), (string) $p->name);
            if ($url === null) {
                $stats['not_found']++;
                $this->line('  <fg=yellow>page not found</>');
                $this->recordFailure((int) $p->id, $apply);
                usleep((int) $this->option('sleep') * 1000);
                continue;
            }
            $html = $this->fetchPage($url);
            if ($html === null) {
                $stats['not_found']++;
                $this->line('  <fg=red>fetch failed</>');
                $this->recordFailure((int) $p->id, $apply);
                usleep((int) $this->option('sleep') * 1000);
                continue;
            }
            $stats['page_found']++;
            $this->line('  page: ' . mb_substr($url, 0, 78));

            $scraped = $this->scrapeSpecs($html);
            $created = $this->writeMapped($p, $catId, $map, $scraped, $apply, $stats);
            if ($apply) {
                $created > 0 ? $this->clearFailure((int) $p->id) : $this->recordFailure((int) $p->id, true);
            }
            usleep((int) $this->option('sleep') * 1000);
        }

        $this->printStats($stats);
        return self::SUCCESS;
    }

    // ── Source: existing products.specs JSON ──────────────────────────────────────

    private function runSpecs(int $supplierId, bool $apply): int
    {
        $categories = $this->option('category')
            ? [(int) $this->option('category')]
            : array_keys(self::MAP_SPECS);

        $stats = ['products' => 0, 'values_created' => 0, 'attrs_created' => 0,
                  'skipped_existing' => 0, 'skipped_no_value' => 0, 'junk_specs' => 0];

        foreach ($categories as $catId) {
            $map = self::MAP_SPECS[$catId] ?? null;
            if (! $map) {
                $this->warn("No specs mapping for category {$catId} — skipped.");
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

            foreach ($q->get(['p.id', 'p.name', 'p.category_id', 'p.specs']) as $p) {
                $specs = json_decode($p->specs ?? '[]', true);
                if (! is_array($specs) || $specs === []) {
                    continue;
                }
                if ($this->isNumericKeyed($specs)) {
                    $stats['junk_specs']++;
                    continue;
                }
                $stats['products']++;
                $this->writeMapped($p, $catId, $map, $specs, $apply, $stats);
            }
        }

        $this->printStats($stats);
        return self::SUCCESS;
    }

    // ── Shared mapping/writing ────────────────────────────────────────────────────

    private function writeMapped(object $p, int $catId, array $map, array $data, bool $apply, array &$stats): int
    {
        $created = 0;
        $printedHeader = isset($stats['page_found']); // scrape already printed a header line
        foreach ($map as $specKey => $mapping) {
            if (! array_key_exists($specKey, $data)) {
                continue;
            }
            $attr = $this->resolveAttribute($catId, $mapping, $apply, $stats);
            if ($attr === null) {
                continue;
            }
            $parsed = $this->parseValue((string) $data[$specKey], $attr->type, (string) ($attr->suffix ?? ''));
            if ($parsed === null) {
                $stats['skipped_no_value']++;
                continue;
            }
            if ($attr->id && DB::table('product_attribute_values')
                    ->where('product_id', $p->id)->where('attribute_id', $attr->id)->exists()) {
                $stats['skipped_existing'] = ($stats['skipped_existing'] ?? 0) + 1;
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
            $created++;
        }

        return $created;
    }

    private function failuresTable(): bool
    {
        return Schema::hasTable('attribute_import_failures');
    }

    private function recordFailure(int $productId, bool $apply): void
    {
        if ($apply && $this->failuresTable()) {
            DB::table('attribute_import_failures')->updateOrInsert(
                ['product_id' => $productId],
                ['attempted_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function clearFailure(int $productId): void
    {
        if ($this->failuresTable()) {
            DB::table('attribute_import_failures')->where('product_id', $productId)->delete();
        }
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
            $existing = DB::table('attributes')->where('category_id', $catId)->where('name', $def['name'])
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
            $stats['attrs_created'] = ($stats['attrs_created'] ?? 0) + 1;
            $this->line("  <fg=green>+ создан атрибут «{$def['name']}» ({$def['type']}" . ($def['suffix'] ? ', ' . $def['suffix'] : '') . ')</>');
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
            if (in_array($low, ['да', 'yes', 'есть', '+', 'true', '1'], true)) {
                return '1';
            }
            if (in_array($low, ['нет', 'no', '-', 'false', '0'], true)) {
                return '0';
            }
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

    private function isNumericKeyed(array $specs): bool
    {
        foreach (array_keys($specs) as $k) {
            if (! is_int($k) && ! ctype_digit((string) $k)) {
                return false;
            }
        }
        return true;
    }

    private function printStats(array $stats): void
    {
        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));
    }
}
