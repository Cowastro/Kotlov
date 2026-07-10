<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditProductContentQualityCommand extends Command
{
    protected $signature = 'products:audit-content-quality
        {--brand= : Brand name or slug}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--reason= : Only show products with this issue reason}
        {--limit=200 : Max sample rows to show, 0 means no samples}
        {--min-issues=1 : Show sample rows with at least this many issues}';

    protected $description = 'Audit product descriptions for legacy HTML, thin text and non-KOTLOV store voice.';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $minIssues = max(1, (int) $this->option('min-issues'));
        $reasonFilter = trim((string) $this->option('reason'));

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->select(
                'p.id',
                'p.sku',
                'p.slug',
                'p.name',
                'p.content',
                'p.short_description',
                'p.video_url',
                'p.documents',
                'b.name as brand',
                'c.name as category'
            )
            ->whereNotNull('p.content')
            ->where('p.content', '<>', '')
            ->orderBy('b.name')
            ->orderBy('p.id');

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where(function ($q) use ($brand) {
                $q->where('b.name', $brand)->orWhere('b.slug', $brand);
            });
        }

        if ((bool) $this->option('not-archived')) {
            $query->where('p.is_archived', false);
        }

        if ((bool) $this->option('active-only') && Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        $summary = [];
        $brandSummary = [];
        $samples = [];
        $checked = 0;
        $productsWithIssues = 0;

        foreach ($query->get() as $row) {
            $checked++;
            $issues = $this->issuesFor((string) $row->content, (string) ($row->short_description ?? ''), $row->documents, (string) ($row->video_url ?? ''));

            if ($reasonFilter !== '' && ! in_array($reasonFilter, $issues, true)) {
                continue;
            }

            foreach ($issues as $issue) {
                $summary[$issue] = ($summary[$issue] ?? 0) + 1;
                $brand = (string) ($row->brand ?: '-');
                $brandSummary[$brand][$issue] = ($brandSummary[$brand][$issue] ?? 0) + 1;
            }

            if ($issues !== []) {
                $productsWithIssues++;
            }

            if ($issues !== [] && count($issues) >= $minIssues && ($limit === 0 || count($samples) < $limit)) {
                $samples[] = [
                    $row->id,
                    $row->sku,
                    $row->brand ?: '-',
                    $row->category ?: '-',
                    implode(', ', $issues),
                    mb_strimwidth((string) $row->name, 0, 52, '...'),
                    Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $row->content))), 180, ''),
                ];
            }
        }

        $this->table(['metric', 'count'], [
            ['checked', $checked],
            ['products_with_issues', $productsWithIssues],
        ]);

        if ($summary !== []) {
            arsort($summary);
            $this->table(
                ['reason', 'products'],
                collect($summary)->map(fn ($count, $reason) => [$reason, $count])->values()->all()
            );
        }

        if ($brandSummary !== []) {
            $brandRows = [];
            foreach ($brandSummary as $brand => $issues) {
                arsort($issues);
                $brandRows[] = [
                    $brand,
                    array_sum($issues),
                    implode('; ', array_map(fn ($reason, $count) => $reason . '=' . $count, array_keys($issues), $issues)),
                ];
            }
            usort($brandRows, fn ($a, $b) => $b[1] <=> $a[1]);
            $this->table(['brand', 'issues', 'reasons'], array_slice($brandRows, 0, 80));
        }

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Brand', 'Category', 'Issues', 'Product', 'Snippet'], $samples);
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function issuesFor(string $content, string $shortDescription, mixed $documents, string $videoUrl): array
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($content)));
        $lower = mb_strtolower($plain);
        $issues = [];

        $checks = [
            'has_inline_image' => '/<img\b/iu',
            'has_inline_link' => '/<a\b/iu',
            'has_inline_iframe' => '/<iframe\b/iu',
            'has_inline_style' => '/\sstyle\s*=/iu',
            'has_unsafe_embed' => '/<(script|object|embed|svg|canvas|picture|video|audio|form|button|input|select|textarea|table)\b/iu',
        ];

        foreach ($checks as $issue => $pattern) {
            if (preg_match($pattern, $content)) {
                $issues[] = $issue;
            }
        }

        $legacyPhrases = [
            'legacy_buy_template' => ['вы можете купить данный товар', 'купить данный товар'],
            'manufacturer_disclaimer' => ['производитель оставляет за собой право'],
            'generic_supplier_voice' => ['обращайтесь к поставщикам', 'у поставщиков', 'у дилеров', 'поставщикам климатического оборудования'],
            'old_promo_text' => ['подарок при покупке', 'мы производим', 'наши топки принесут'],
        ];

        foreach ($legacyPhrases as $issue => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    $issues[] = $issue;
                    break;
                }
            }
        }

        if (mb_strlen($plain) < 180) {
            $issues[] = 'thin_content';
        }

        if ($shortDescription === '' || mb_strlen(trim(strip_tags($shortDescription))) < 80) {
            $issues[] = 'thin_short_description';
        }

        if ($this->documentsCount($documents) > 0 && $videoUrl === '' && preg_match('/youtube|youtu\.be|rutube|vimeo/iu', $content)) {
            $issues[] = 'video_link_not_extracted';
        }

        return array_values(array_unique($issues));
    }

    private function documentsCount(mixed $documents): int
    {
        $decoded = is_string($documents) ? json_decode($documents, true) : $documents;

        return is_array($decoded) ? count($decoded) : 0;
    }
}
