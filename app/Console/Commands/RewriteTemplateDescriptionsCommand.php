<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cards still carrying the local fallback text of supplier:enrich-teplodvor («… — комплектующее для инженерной системы
 * бренда …», «Перед заказом сверьте характеристики модели…») get a real description from the AI enricher, written
 * only from the card's own characteristics. The dry run counts the cards per brand; --apply rewrites up to --limit.
 *
 *   php artisan catalog:rewrite-template-descriptions                    # count per brand
 *   php artisan catalog:rewrite-template-descriptions --apply --limit=40
 */
class RewriteTemplateDescriptionsCommand extends Command
{
    protected $signature = 'catalog:rewrite-template-descriptions
        {--apply : Rewrite the descriptions}
        {--limit=40 : Cards per run}
        {--brand= : Only this brand slug}';

    protected $description = 'Replace the template «комплектующее для инженерной системы» descriptions with AI descriptions from the card data';

    public const MARKER = 'Перед заказом сверьте характеристики модели с проектом системы и инструкцией производителя.';

    public function handle(AiContentEnricher $ai): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));
        $query = Product::query()
            ->with('brand:id,name,slug')
            ->where('is_archived', false)
            ->where('content', 'like', '%'.self::MARKER.'%')
            ->when($this->option('brand'), fn ($q, $slug) => $q->whereHas('brand', fn ($b) => $b->where('slug', $slug)))
            ->orderByDesc('is_active')
            ->orderBy('id');

        $all = (clone $query)->get(['id', 'brand_id', 'is_active']);
        $stats = ['total' => $all->count(), 'active' => $all->where('is_active', true)->count(), 'rewritten' => 0, 'failed' => 0];
        $all->groupBy(fn (Product $p): string => $p->brand?->slug ?: 'no-brand')
            ->map->count()->sortDesc()
            ->each(fn (int $count, string $slug) => $this->line('audit.template_descriptions.brand='.Str::slug($slug).':'.$count));

        if ($apply) {
            if (! $ai->isAvailable()) {
                $this->error('ИИ не настроен.');

                return self::FAILURE;
            }
            foreach ($query->limit($limit)->get() as $product) {
                $brand = $product->brand?->name ?: '';
                $specs = collect((array) $product->specs)
                    ->filter(fn ($spec): bool => is_array($spec)
                        && ! in_array(mb_strtolower(trim((string) ($spec['key'] ?? ''))), ['артикул производителя', 'штрихкод'], true))
                    ->values()->all();
                $content = $ai->enrich((string) $product->name, $brand, null, $specs);
                if ($content === null || mb_strlen(trim(strip_tags($content))) < 300 || str_contains($content, self::MARKER)) {
                    $stats['failed']++;
                    $this->line('audit.template_descriptions.failed='.$product->id);

                    continue;
                }
                $short = $ai->shortDescription((string) $product->name, $brand, $specs)
                    ?: Str::limit(trim(strip_tags($content)), 240, '');
                $product->forceFill([
                    'content' => strip_tags($content, '<p><ul><li><strong><h2><h3>'),
                    'short_description' => Str::limit(trim($short), 240, ''),
                    'meta_description' => Str::limit(trim($short), 160, ''),
                ])->save();
                $stats['rewritten']++;
                $this->line('audit.template_descriptions.done='.$product->id);
            }
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.template_descriptions.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }
}
