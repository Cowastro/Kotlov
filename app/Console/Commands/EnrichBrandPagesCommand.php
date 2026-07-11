<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrichBrandPagesCommand extends Command
{
    protected $signature = 'brands:enrich-pages
        {--apply : Write only empty brand fields}
        {--brand= : One brand name or slug}
        {--limit=50 : Maximum brands, 0 means no limit}
        {--include-weak : Also rewrite obviously thin/non-store brand text}
        {--min-content-chars=260 : Content shorter than this can be treated as weak with --include-weak}
        {--audit-only : Only report brands needing work without calling AI}
        {--preview : Print generated content snippets to the console}
        {--openai : Use OPENAI_API_KEY/OPENAI_API_URL for this run when configured}
        {--model= : Override OpenAI-compatible AI model for this run}';

    protected $description = 'Fill empty brand page SEO fields from real catalog signals without overwriting existing content.';

    public function handle(AiContentEnricher $ai): int
    {
        if ((bool) $this->option('openai')) {
            $ai = $ai->withOpenAi((string) $this->option('model'));
        } elseif (trim((string) $this->option('model')) !== '') {
            $ai = $ai->withModel((string) $this->option('model'));
        }

        $query = DB::table('brands as b')
            ->join('products as p', 'p.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('b.is_active', 1)
            ->where('p.is_archived', 0)
            ->select('b.id', 'b.name', 'b.slug', 'b.country', 'b.h1', 'b.content', 'b.meta_title', 'b.meta_keywords', 'b.meta_description')
            ->selectRaw('GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR " | ") as product_names')
            ->selectRaw('GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") as category_names')
            ->groupBy('b.id', 'b.name', 'b.slug', 'b.country', 'b.h1', 'b.content', 'b.meta_title', 'b.meta_keywords', 'b.meta_description')
            ->orderBy('b.name');

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where(function ($q) use ($brand): void {
                $q->where('b.name', 'like', '%' . $brand . '%')
                    ->orWhere('b.slug', 'like', '%' . Str::slug($brand) . '%');
            });
        }

        $limit = max(0, (int) $this->option('limit'));
        $rows = $query->get();
        if ($limit > 0) {
            $rows = $rows->take($limit);
        }

        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'APPLY: only empty brand fields will be written.' : 'DRY RUN: no brand fields will be changed.');
        $this->line('AI provider: ' . $ai->providerName());

        $includeWeak = (bool) $this->option('include-weak');
        $auditOnly = (bool) $this->option('audit-only');
        $preview = (bool) $this->option('preview');
        $minContentChars = max(80, (int) $this->option('min-content-chars'));
        $auditRows = [];

        $summary = [
            'checked' => 0,
            'needs_work' => 0,
            'generated' => 0,
            'updated' => 0,
            'skipped_existing' => 0,
            'skipped_weak_protected' => 0,
            'errors' => 0,
        ];

        foreach ($rows as $row) {
            $summary['checked']++;
            $emptyContent = trim((string) $row->content) === '';
            $emptyMeta = trim((string) $row->meta_description) === '';
            $emptyTitle = trim((string) $row->meta_title) === '';
            $emptyKeywords = trim((string) $row->meta_keywords) === '';
            $emptyH1 = trim((string) $row->h1) === '';
            $weakContent = ! $emptyContent && $this->isWeakContent((string) $row->content, $minContentChars);
            $weakMeta = ! $emptyMeta && $this->isWeakMeta((string) $row->meta_description);

            if (! ($emptyContent || $emptyMeta || $emptyTitle || $emptyKeywords || $emptyH1 || $weakContent || $weakMeta)) {
                $summary['skipped_existing']++;
                continue;
            }

            if (($weakContent || $weakMeta) && ! $includeWeak && ! ($emptyContent || $emptyMeta || $emptyTitle || $emptyKeywords || $emptyH1)) {
                $summary['skipped_weak_protected']++;
                continue;
            }

            $summary['needs_work']++;
            if ($auditOnly) {
                $auditRows[] = [
                    $row->id,
                    $row->slug,
                    $row->name,
                    $emptyH1 ? 'empty' : 'ok',
                    $emptyContent ? 'empty' : ($weakContent ? 'weak' : 'ok'),
                    $emptyMeta ? 'empty' : ($weakMeta ? 'weak' : 'ok'),
                    $emptyTitle ? 'empty' : 'ok',
                    $emptyKeywords ? 'empty' : 'ok',
                ];
                continue;
            }

            $data = [];
            if ($emptyH1) {
                $data['h1'] = 'Бренд ' . $row->name;
            }
            if ($emptyTitle) {
                $data['meta_title'] = $row->name . ' — купить в Беларуси | KOTLOV.BY';
            }
            if ($emptyKeywords) {
                $data['meta_keywords'] = implode(', ', array_filter([$row->name, $row->category_names, 'купить ' . $row->name, 'KOTLOV.BY']));
            }

            $shouldGenerateContent = $emptyContent || $emptyMeta || ($includeWeak && ($weakContent || $weakMeta));
            if ($shouldGenerateContent) {
                $prompt = $this->promptFor($row);
                $generated = $this->parseJson($ai->complete($prompt, 900));
                if ($generated === null) {
                    $summary['errors']++;
                    $this->warn('AI failed: ' . $row->name);
                    continue;
                }
                $summary['generated']++;
                if (($emptyContent || ($includeWeak && $weakContent)) && ($generated['content'] ?? '') !== '') {
                    $data['content'] = $generated['content'];
                }
                if (($emptyMeta || ($includeWeak && $weakMeta)) && ($generated['meta_description'] ?? '') !== '') {
                    $data['meta_description'] = $generated['meta_description'];
                }
            }

            if ($data === []) {
                continue;
            }

            $this->line(sprintf('%s | %s', $apply ? 'UPDATE' : 'WOULD UPDATE', $row->name));
            if ($preview) {
                if (isset($data['content'])) {
                    $this->line(mb_substr(strip_tags($data['content']), 0, 600));
                }
                if (isset($data['meta_description'])) {
                    $this->line('meta: ' . $data['meta_description']);
                }
            }
            if ($apply) {
                $data['updated_at'] = now();
                DB::table('brands')->where('id', $row->id)->update($data);
                $summary['updated']++;
            }
        }

        $this->table(['metric', 'count'], collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($auditOnly && $auditRows !== []) {
            $this->table(['id', 'slug', 'brand', 'h1', 'content', 'meta', 'title', 'keywords'], array_slice($auditRows, 0, 120));
        }

        return self::SUCCESS;
    }

    private function promptFor(object $row): string
    {
        $country = trim((string) $row->country) !== '' ? 'Страна из базы: ' . $row->country : 'Страна в базе не указана';
        $productNames = $this->relevantProductExamples($row)
            ->take(18)
            ->implode(' | ');
        $categoryNames = $this->relevantCategoryNames($row);
        $productLine = $productNames !== ''
            ? "Проверенные примеры названий товаров этого бренда из каталога: {$productNames}"
            : 'Проверенных примеров названий товаров нет: опирайся только на бренд и категории, не перечисляй модели.';

        return <<<PROMPT
Ты пишешь страницу бренда для интернет-магазина отопительного, сантехнического и климатического оборудования KOTLOV.BY.
Бренд: {$row->name}
{$country}
Категории товаров бренда в каталоге: {$categoryNames}
{$productLine}

Верни только валидный JSON без Markdown: {"content":"...","meta_description":"..."}
Пиши по-русски, в спокойном коммерческом стиле интернет-магазина.
Используй только входные данные: не придумывай историю бренда, завод, сертификаты, технологии, годы работы, официальный статус, гарантию, страну сверх базы и технические характеристики.
Если в категориях или примерах есть очевидно чужой бренд, не упоминай этот товар и не делай по нему выводы.
Не пиши, что бренд "специализируется", "производит" или "известен", если этого нет во входных данных. Лучше формулируй: "в каталоге KOTLOV.BY бренд представлен ...".
Не расшифровывай модельные индексы и маркировки (например, типы, серии, цифры в названии), если расшифровка не дана во входных данных.
Не давай инженерных рекомендаций с неподтвержденными параметрами: "средней мощности", "для помещений X м²", "однорядный/двухрядный", "для типовых квартир" и похожее разрешено только если это прямо есть во входных названиях или категориях.
Не обещай наличие, сроки, скидки, гарантию, сервисный статус и цену. Не используй фразу "в наличии" в тексте бренда.
Не отправляй покупателя к поставщикам, дилерам и на сторонние сайты. KOTLOV.BY продает эти товары сам.

content:
- 3-5 полезных HTML-абзацев <p>; допускается один <h2> и один короткий <ul><li>.
- Текст должен объяснять, какие товары бренда есть в каталоге и как их выбирать по назначению, но без таблицы характеристик.
- Если нужно сказать про подбор, формулируй безопасно: "ориентируйтесь на параметры в карточке товара", "сравнивайте размеры, тип подключения и назначение, если они указаны в карточке".
- Естественно добавь локальный SEO-блок: можно подобрать, заказать или купить товары бренда на KOTLOV.BY в %city% с доставкой по Беларуси.
- Не пиши техническую выжимку из названий; сделай текст удобным для покупателя.
meta_description: 140-170 символов, без %city%, цен и неподтвержденных обещаний.
PROMPT;
    }

    private function parseJson(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            return null;
        }

        return [
            'content' => $this->sanitizeContent((string) ($data['content'] ?? '')),
            'meta_description' => $this->sanitizeMeta((string) ($data['meta_description'] ?? '')),
        ];
    }

    private function relevantProductExamples(object $row): \Illuminate\Support\Collection
    {
        $brandKey = $this->nameKey((string) $row->name);
        $brandTokens = collect(preg_split('/\s+/u', $brandKey) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->values();

        $names = collect(explode(' | ', (string) $row->product_names))
            ->map(fn (string $name): string => trim($name))
            ->filter();

        if ($brandTokens->isEmpty()) {
            return $names->take(8);
        }

        $matched = $names->filter(function (string $name) use ($brandTokens): bool {
            $key = $this->nameKey($name);

            return $brandTokens->contains(fn (string $token): bool => str_contains($key, $token));
        })->values();

        return $matched->isNotEmpty() ? $matched : collect();
    }

    private function relevantCategoryNames(object $row): string
    {
        $brandKey = $this->nameKey((string) $row->name);
        $brandTokens = collect(preg_split('/\s+/u', $brandKey) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->values();

        if ($brandTokens->isEmpty()) {
            return (string) $row->category_names;
        }

        $products = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.brand_id', (int) $row->id)
            ->where('p.is_archived', 0)
            ->get(['p.name', 'c.name as category']);

        $categories = $products
            ->filter(function ($product) use ($brandTokens): bool {
                $key = $this->nameKey((string) $product->name);

                return $brandTokens->contains(fn (string $token): bool => str_contains($key, $token));
            })
            ->pluck('category')
            ->filter()
            ->unique()
            ->values();

        return $categories->isNotEmpty()
            ? $categories->implode(', ')
            : (string) $row->category_names;
    }

    private function nameKey(string $value): string
    {
        $value = Str::lower($value);
        $value = str_replace(['ё', 'Ё'], ['е', 'е'], $value);
        $value = preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function isWeakContent(string $content, int $minChars): bool
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');

        if ($plain === '') {
            return true;
        }

        if (mb_strlen($plain) < $minChars) {
            return true;
        }

        return (bool) preg_match('/(обращайтесь к поставщикам|у поставщиков|у дилеров|вставьте|lorem|описание скоро|товары бренда представлены)/iu', $plain);
    }

    private function isWeakMeta(string $meta): bool
    {
        $meta = trim($meta);

        return $meta === ''
            || mb_strlen($meta) < 80
            || (bool) preg_match('/(%city%|lorem|описание скоро|у поставщиков|у дилеров)/iu', $meta);
    }

    private function sanitizeContent(string $content): string
    {
        $content = trim(strip_tags($content, '<p><ul><li><h2><h3><strong>'));
        $content = preg_replace('/\s+([,.!?;:])/u', '$1', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/u', ' ', $content) ?? $content;

        $replacements = [
            '/(?:бренд\s+)?([A-Za-zА-Яа-я0-9\-\s]+)\s+хорошо\s+известен[^.!?]*[.!?]?/iu' => 'Бренд представлен в каталоге KOTLOV.BY. ',
            '/(?:бренд\s+)?([A-Za-zА-Яа-я0-9\-\s]+)\s+известен[^.!?]*[.!?]?/iu' => 'Бренд представлен в каталоге KOTLOV.BY. ',
            '/подходят\s+для\s+большинства\s+современных\s+систем\s+отопления/iu' => 'подбираются по параметрам конкретной системы отопления',
            '/обращайтесь\s+к\s+поставщикам[^.!?]*[.!?]?/iu' => 'Заказать товары бренда можно на KOTLOV.BY в %city%.',
            '/у\s+(?:поставщиков|дилеров|продавцов)/iu' => 'на KOTLOV.BY',
            '/через\s+(?:поставщиков|дилеров|продавцов)/iu' => 'на KOTLOV.BY',
            '/\bв\s+наличии\b/iu' => 'в каталоге',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content) ?? $content;
        }

        return trim($content);
    }

    private function sanitizeMeta(string $meta): string
    {
        $meta = trim(strip_tags($meta));
        $meta = str_replace('%city%', 'Беларуси', $meta);
        $meta = preg_replace('/\s+([,.!?;:])/u', '$1', $meta) ?? $meta;

        return trim($meta);
    }
}
