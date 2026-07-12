<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RepairVarmegaFittingContentCommand extends Command
{
    protected $signature = 'products:repair-varmega-fitting-content
        {--apply : Write repaired descriptions}
        {--product= : Product ID or SKU}
        {--article-prefix= : Supplier article prefix, e.g. VM701}
        {--category=Пресс-фитинги : Category name filter}
        {--force : Repair even when suspicious terms are not detected}
        {--limit=50 : Rows to process, 0 means all}
        {--offset=0 : Skip first N rows}';

    protected $description = 'Replace hallucinated Varmega fitting descriptions with safe article/size-based text.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Varmega fitting content will be repaired.</>'
            : '<fg=yellow;options=bold>DRY RUN: Varmega fitting content repair preview only.</>');

        $query = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('b.name', 'Varmega')
            ->where('s.code', 'rn-profi')
            ->where('p.is_archived', false)
            ->where('c.name', 'like', '%' . trim((string) $this->option('category')) . '%')
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.content',
                'p.short_description',
                'p.meta_description',
                'c.name as category_name',
                DB::raw('COALESCE(sp.supplier_article, "") as supplier_article'),
            ])
            ->orderBy('p.id');

        if ($product = trim((string) $this->option('product'))) {
            $query->where(function ($query) use ($product): void {
                $query->where('p.sku', $product);
                if (ctype_digit($product)) {
                    $query->orWhere('p.id', (int) $product);
                }
            });
        }

        if ($prefix = trim((string) $this->option('article-prefix'))) {
            $query->where('sp.supplier_article', 'like', $prefix . '%');
        }

        $total = (clone $query)->count();

        if ($offset > 0) {
            $query->offset($offset);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $stats = [
            'checked' => 0,
            'dirty' => 0,
            'written' => 0,
            'skipped_clean' => 0,
        ];

        $examples = [];

        foreach ($rows as $row) {
            $stats['checked']++;
            $dirty = $this->hasSuspiciousTerms($this->textForAudit($row));

            if (! $dirty && ! $force) {
                $stats['skipped_clean']++;
                continue;
            }

            $stats['dirty']++;
            $article = $this->article($row);
            $size = $this->size($row);
            $short = $this->shortDescription((string) $row->name, $article, $size);
            $content = $this->content((string) $row->name, $article, $size);

            if (count($examples) < 30) {
                $examples[] = [
                    $row->id,
                    $row->sku,
                    $article,
                    $size ?: '-',
                    mb_substr($this->plainText((string) $row->content), 0, 70),
                    mb_substr($short, 0, 90),
                ];
            }

            if (! $apply) {
                continue;
            }

            $data = [
                'short_description' => $short,
                'content' => $content,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('products', 'meta_description')) {
                $data['meta_description'] = Str::limit($short . ' Купить на KOTLOV.BY с доставкой по Беларуси.', 250, '');
            }

            DB::table('products')->where('id', $row->id)->update($data);
            $stats['written']++;
        }

        $this->table(['metric', 'count'], [
            ['total_scope', $total],
            ['checked', $stats['checked']],
            ['dirty_or_forced', $stats['dirty']],
            ['written', $stats['written']],
            ['skipped_clean', $stats['skipped_clean']],
        ]);

        $this->table(
            ['ID', 'SKU', 'Article', 'Size', 'Old snippet', 'New short'],
            $examples
        );

        return self::SUCCESS;
    }

    private function textForAudit(object $row): string
    {
        return implode(' ', [
            (string) ($row->content ?? ''),
            (string) ($row->short_description ?? ''),
            (string) ($row->meta_description ?? ''),
        ]);
    }

    private function hasSuspiciousTerms(string $value): bool
    {
        $text = mb_strtolower($this->plainText($value));
        $terms = [
            'котел', 'котёл', 'котла', 'котлы', 'котлов',
            'твердотопливный', 'твердотопливные',
            'радиатор', 'радиаторы', 'радиаторный', 'радиаторная',
            'бойлер', 'водонагреватель',
            'насос', 'насосный',
            'горелка', 'печь', 'камин',
            'квт',
        ];

        foreach ($terms as $term) {
            if (preg_match('/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/iu', $text)) {
                return true;
            }
        }

        return false;
    }

    private function article(object $row): string
    {
        $article = trim((string) ($row->supplier_article ?? ''));
        if ($article !== '') {
            return $article;
        }

        if (preg_match('/\bVM[A-Z0-9-]{4,}\b/u', (string) $row->name, $m)) {
            return $m[0];
        }

        return '';
    }

    private function size(object $row): string
    {
        $name = (string) $row->name;
        $article = $this->article($row);
        if ($article !== '') {
            $name = trim((string) preg_replace('/\b' . preg_quote($article, '/') . '\b/u', ' ', $name));
        }

        if (preg_match('/(\d+(?:[.,]\d+)?\s*(?:x|х|×|a)\s*\d+(?:[.,]\d+)?(?:\s*(?:x|х|×|a)\s*\d+(?:[.,]\d+)?)?|\d+\s*x\s*\d+|\d+\s*\/\s*\d+"?|\d+\s*1\/\d+")/iu', $name, $m)) {
            return trim(str_replace(['х', '×', 'a'], 'x', $m[1]));
        }

        return '';
    }

    private function shortDescription(string $name, string $article, string $size): string
    {
        $label = $article !== '' ? 'Varmega ' . $article : trim($name);
        if ($size !== '' && ! str_contains($label, $size)) {
            $label .= ' ' . $size;
        }

        return Str::limit(
            $label . ' — пресс-фитинг Varmega Inox Press для трубопроводных систем из нержавеющей стали. Подбирается по артикулу и размеру соединения.',
            240,
            ''
        );
    }

    private function content(string $name, string $article, string $size): string
    {
        $label = e($article !== '' ? 'Varmega ' . $article : trim($name));
        $sizeText = $size !== '' ? ' Размер соединения: ' . e($size) . '.' : '';

        return '<p>' . $label . ' — пресс-фитинг Varmega Inox Press для монтажа трубопроводных систем из нержавеющей стали профиля V.' . $sizeText . '</p>'
            . '<p>Карточка сформирована строго по артикулу поставщика. Перед заказом сверяйте артикул, размер и совместимость с используемой трубой Varmega Inox Press.</p>';
    }

    private function plainText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
