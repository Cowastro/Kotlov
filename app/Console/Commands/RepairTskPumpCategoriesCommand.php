<?php

namespace App\Console\Commands;

use App\Support\Catalog\TskPumpClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairTskPumpCategoriesCommand extends Command
{
    protected $signature = 'supplier:repair-tsk-pump-categories
        {--apply : Apply category and description corrections}
        {--limit= : Inspect only the first N supplier links}';

    protected $description = 'Audit and repair pump types/categories for products linked to ТСК Насосы.';

    private const SUPPLIER_CODE = 'tsk_nasosy';

    /** Category IDs are only a compatibility fallback; slugs are authoritative. */
    private const CATEGORY_FALLBACKS = [
        'membrannyie-baki' => 89,
        'drenajnyie' => 265,
        'tsirkulyatsionnyie' => 60,
        'poverhnostnyie' => 249,
        'nasosnyie-stantsii' => 251,
        'skvajinnye-nasosy' => 272,
        'komplektuyushchie-dlya-nasosov' => 0,
        'filtry' => 0,
        'mnogostupenchatye-nasosy' => 0,
        'konsolnye-i-monoblochnye-nasosy' => 0,
        'in-lain-nasosy' => 0,
        'kanalizatsionnye-nasosy-sukhoy-ustanovki' => 0,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);

        if ($supplierId === 0) {
            $this->error('Supplier tsk_nasosy not found.');

            return self::FAILURE;
        }

        $categories = $this->categoryIds();
        $query = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sp.supplier_id', $supplierId)
            ->whereNotNull('sp.product_id')
            ->where('p.is_archived', false)
            ->orderBy('p.id')
            ->select([
                'p.id', 'p.name', 'p.slug', 'p.category_id', 'p.content', 'p.short_description',
                'sp.supplier_name', 'sp.supplier_article', 'c.slug as current_category_slug',
            ]);

        $limit = max(0, (int) ($this->option('limit') ?? 0));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $stats = [
            'inspected' => 0,
            'classified' => 0,
            'category_changes' => 0,
            'description_changes' => 0,
            'unchanged' => 0,
            'unclassified' => 0,
            'errors' => 0,
        ];
        $byType = [];

        foreach ($query->get() as $row) {
            $stats['inspected']++;
            $classificationText = trim($row->name.' '.$row->supplier_name);
            $targetSlug = TskPumpClassifier::categorySlug($classificationText);

            if ($targetSlug === null || ! isset($categories[$targetSlug])) {
                $stats['unclassified']++;
                continue;
            }

            $stats['classified']++;
            $byType[$targetSlug] = ($byType[$targetSlug] ?? 0) + 1;
            $update = [];

            if ((int) $row->category_id !== $categories[$targetSlug]) {
                $update['category_id'] = $categories[$targetSlug];
                $stats['category_changes']++;
            }

            if ($this->needsSurfaceDescriptionRepair($row, $targetSlug)) {
                $description = $this->surfaceDescription((string) $row->name);
                $update['content'] = $description['content'];
                $update['short_description'] = $description['short'];
                $update['meta_description'] = $description['meta'];
                $stats['description_changes']++;
            }

            if ($update === []) {
                $stats['unchanged']++;
                continue;
            }

            $this->line(sprintf(
                'audit.tsk_repair.product=%d:%s:%s:%s:%d',
                (int) $row->id,
                preg_replace('/[^a-z0-9._-]/', '', (string) $row->slug),
                preg_replace('/[^a-z0-9_-]/', '', (string) $row->current_category_slug),
                str_replace('-', '_', $targetSlug),
                isset($update['content']) ? 1 : 0,
            ));

            if (! $apply) {
                continue;
            }

            try {
                $update['updated_at'] = now();
                DB::table('products')->where('id', $row->id)->update($update);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('Failed product #'.(int) $row->id.': '.$e->getMessage());
            }
        }

        foreach ($byType as $slug => $count) {
            $stats['type_'.str_replace('-', '_', $slug)] = $count;
        }

        $this->line($apply ? 'APPLY completed.' : 'DRY RUN completed.');
        foreach ($stats as $name => $value) {
            $this->line("metric.tsk_repair.{$name}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, int> */
    private function categoryIds(): array
    {
        $ids = [];
        foreach (self::CATEGORY_FALLBACKS as $slug => $fallback) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id === null && $fallback > 0 && DB::table('categories')->where('id', $fallback)->exists()) {
                $id = $fallback;
            }
            if ($id !== null) {
                $ids[$slug] = (int) $id;
            }
        }

        return $ids;
    }

    private function needsSurfaceDescriptionRepair(object $row, string $targetSlug): bool
    {
        if ($targetSlug !== 'poverhnostnyie') {
            return false;
        }

        $name = mb_strtolower((string) $row->name);
        if (! preg_match('/\bpsb[\s_-]*\d/i', $name)) {
            return false;
        }

        $text = mb_strtolower(strip_tags((string) $row->content.' '.(string) $row->short_description));

        return $text === ''
            || str_contains($text, 'скважинный насос')
            || str_contains($text, 'скважинного насоса')
            || str_contains($text, 'погружной насос')
            || str_contains($text, 'погружного насоса')
            || str_contains($text, 'место монтажа в скважине');
    }

    /** @return array{content:string,short:string,meta:string} */
    private function surfaceDescription(string $name): array
    {
        $short = $name.' — поверхностный многоступенчатый насос со встроенным частотным преобразователем для автоматического водоснабжения и стабильного давления.';
        $content = '<p><strong>'.e($name).'</strong> — поверхностный многоступенчатый центробежный насос со встроенным частотным преобразователем. Он автоматически поддерживает заданное давление и изменяет производительность в зависимости от расхода воды.</p>'
            .'<p>Насос предназначен для сухой установки в системе водоснабжения. Оборудование не опускают в скважину: всасывающую магистраль подключают к источнику воды с соблюдением требований инструкции.</p>';

        return [
            'content' => $content,
            'short' => mb_substr($short, 0, 240),
            'meta' => mb_substr($short.' Цена и заказ с доставкой по Беларуси.', 0, 250),
        ];
    }
}
