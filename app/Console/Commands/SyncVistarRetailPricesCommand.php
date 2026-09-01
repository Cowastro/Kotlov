<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncVistarRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-vistar-retail
        {--apply : Write matched retail prices; default is dry-run}
        {--include-inactive : Allow inactive products to be matched}
        {--export= : Write matched products as a CSV export path under public/exports}
        {--export-sbg-existing= : Crawl public sbg.by and write only existing SBG product price updates under public/exports}
        {--include-unchanged-sbg : Include existing SBG matches even when the price is already current}
        {--delete-export= : Delete a VISTAR CSV export path under public/exports and exit}';

    protected $description = 'Sync safe retail prices from VISTAR Price 01.09.2026.';

    private const DATA_FILE = 'database/data/vistar-price-2026-09-01.json';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('delete-export')) {
            $this->deleteExport((string) $this->option('delete-export'));

            return self::SUCCESS;
        }

        $data = $this->loadData();

        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('export-sbg-existing')) {
            return $this->exportExistingSbgProducts($data, (string) $this->option('export-sbg-existing'));
        }

        $index = $this->productIndex();
        $stats = [
            'rows' => count($data['rows']),
            'matched' => 0,
            'price_updates' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'invalid' => 0,
        ];
        $details = [];
        $exportRows = [];

        foreach ($data['rows'] as $row) {
            $brand = $this->brandFor($row);
            $retail = $this->money($row['retail_byn'] ?? null);

            if ($brand === null || $retail === null) {
                $stats['invalid']++;
                continue;
            }

            $key = $this->keyFor($brand, (string) $row['name'], (string) ($row['section'] ?? ''));
            $matches = $index[$key] ?? collect();

            if ($matches->count() === 0) {
                $stats['unmatched']++;
                continue;
            }

            if ($matches->count() > 1) {
                $stats['ambiguous']++;
                $details[] = [
                    $row['sheet'],
                    $row['article'],
                    $row['name'],
                    $retail,
                    'ambiguous: ' . $matches->pluck('id')->implode(','),
                ];
                continue;
            }

            /** @var object $product */
            $product = $matches->first();
            $stats['matched']++;
            $changed = abs((float) $product->price - $retail) >= 0.005;
            $stats[$changed ? 'price_updates' : 'unchanged']++;

            $details[] = [
                $row['sheet'],
                $row['article'],
                mb_substr((string) $row['name'], 0, 42),
                number_format((float) $product->price, 2, '.', '') . ' -> ' . number_format($retail, 2, '.', ''),
                '#' . $product->id . ' ' . mb_substr((string) $product->name, 0, 52),
            ];
            $exportRows[] = [
                'Код товара' => (string) ($product->sku ?? $product->id),
                'Название' => (string) $product->name,
                'Цена' => number_format($retail, 2, '.', ''),
                'Валюта' => (string) ($product->currency ?? 'BYN'),
                'Наличие' => 'В наличии',
                'ID KOTLOV' => (string) $product->id,
                'Бренд' => (string) $product->brand,
                'Артикул VISTAR' => (string) ($row['article'] ?? ''),
                'Название VISTAR' => (string) ($row['name'] ?? ''),
                'Лист VISTAR' => (string) ($row['sheet'] ?? ''),
            ];

            if ($apply && $changed) {
                Product::query()
                    ->where('id', (int) $product->id)
                    ->update([
                        'price' => $retail,
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: VISTAR retail prices were written.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database changes.</>');

        $this->line(sprintf(
            'Source: %s, date: %s',
            $data['source_file'] ?? self::DATA_FILE,
            $data['price_date'] ?? 'unknown'
        ));

        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        $this->table(['sheet', 'article', 'price-list name', 'price', 'product'], array_slice($details, 0, 80));

        if ($this->option('export')) {
            $this->writeExport((string) $this->option('export'), $exportRows);
        }

        return self::SUCCESS;
    }

    /**
     * @param array{rows:array<int,array<string,mixed>>} $data
     */
    private function exportExistingSbgProducts(array $data, string $relativePath): int
    {
        $vistarIndex = $this->vistarIndex($data);
        $urls = $this->discoverSbgProductUrls();
        $products = [];
        $stats = [
            'sbg_urls' => count($urls),
            'sbg_loaded' => 0,
            'matched_existing' => 0,
            'price_updates' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
        ];
        $rows = [];
        $includeUnchanged = (bool) $this->option('include-unchanged-sbg');

        foreach ($urls as $url) {
            $product = $this->fetchSbgProduct($url);

            if ($product === null) {
                continue;
            }

            $stats['sbg_loaded']++;
            $products[] = $product;
        }

        $sbgIndex = collect($products)->groupBy('key');

        foreach ($sbgIndex as $key => $matches) {
            if (! isset($vistarIndex[$key])) {
                $stats['unmatched'] += $matches->count();
                continue;
            }

            if ($matches->count() !== 1 || count($vistarIndex[$key]) !== 1) {
                $stats['ambiguous']++;
                continue;
            }

            $product = $matches->first();
            $vistar = $vistarIndex[$key][0];
            $retail = (float) $vistar['retail'];
            $current = (float) $product['price'];
            $changed = abs($current - $retail) >= 0.005;

            $stats['matched_existing']++;
            $stats[$changed ? 'price_updates' : 'unchanged']++;

            if (! $changed && ! $includeUnchanged) {
                continue;
            }

            $rows[] = [
                'ID товара' => $product['id'],
                'Название' => $product['title'],
                'Цена' => number_format($retail, 2, '.', ''),
                'Валюта' => 'BYN',
                'Наличие' => 'В наличии',
                'Ссылка SBG' => $product['url'],
                'Текущая цена SBG' => number_format($current, 2, '.', ''),
                'Артикул VISTAR' => (string) ($vistar['article'] ?? ''),
                'Название VISTAR' => (string) ($vistar['name'] ?? ''),
                'Лист VISTAR' => (string) ($vistar['sheet'] ?? ''),
            ];
        }

        $this->line('<fg=yellow;options=bold>SBG export: existing public products only, no new products.</>');
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        $this->writeSbgExport($relativePath, $rows);

        return self::SUCCESS;
    }

    /**
     * @param array{rows:array<int,array<string,mixed>>} $data
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function vistarIndex(array $data): array
    {
        $index = [];

        foreach ($data['rows'] as $row) {
            $brand = $this->brandFor($row);
            $retail = $this->money($row['retail_byn'] ?? null);

            if ($brand === null || $retail === null) {
                continue;
            }

            $key = $this->keyFor($brand, (string) $row['name'], (string) ($row['section'] ?? ''));
            $row['retail'] = $retail;
            $index[$key][] = $row;
        }

        return $index;
    }

    /**
     * @return array<int,string>
     */
    private function discoverSbgProductUrls(): array
    {
        $urls = [];

        foreach (['baxi', 'eco-4s', 'ampera'] as $term) {
            $html = $this->httpGet('https://sbg.by/site_search?search_term=' . rawurlencode($term));
            if ($html === null) {
                continue;
            }

            if (preg_match_all('/href=["\'](https:\/\/sbg\.by)?(\/p\d+-[^"\']+\.html)["\']/u', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $urls['https://sbg.by' . $match[2]] = true;
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * @return array{id:string,url:string,title:string,price:float,key:string}|null
     */
    private function fetchSbgProduct(string $url): ?array
    {
        $html = $this->httpGet($url);

        if ($html === null || ! preg_match('/\/p(\d+)-/u', $url, $idMatch)) {
            return null;
        }

        $title = $this->extractSbgTitle($html);
        $price = $this->extractSbgPrice($html);

        if ($title === null || $price === null || ! str_contains(mb_strtoupper($title), 'BAXI')) {
            return null;
        }

        return [
            'id' => $idMatch[1],
            'url' => $url,
            'title' => $title,
            'price' => $price,
            'key' => $this->keyFor('BAXI', $title),
        ];
    }

    private function extractSbgTitle(string $html): ?string
    {
        if (! preg_match('/<h1[^>]*>(.*?)<\/h1>/isu', $html, $match)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return $title !== '' ? $title : null;
    }

    private function extractSbgPrice(string $html): ?float
    {
        foreach ([
            '/"price"\s*:\s*"?([0-9]+(?:[.,][0-9]+)?)/u',
            '/ec_price_original["\']?\s*[:=]\s*["\']?([0-9]+(?:[.,][0-9]+)?)/u',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return $this->money($match[1]);
            }
        }

        return null;
    }

    private function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovPriceAudit/1.0)\r\n",
                'timeout' => 20,
            ],
        ]);
        $html = @file_get_contents($url, false, $context);

        return is_string($html) && $html !== '' ? $html : null;
    }

    /**
     * @return array{source_file?:string,price_date?:string,rows:array<int,array<string,mixed>>}|null
     */
    private function loadData(): ?array
    {
        $path = base_path(self::DATA_FILE);
        $data = json_decode((string) @file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['rows'] ?? null)) {
            $this->error('Cannot read VISTAR data file: ' . $path);

            return null;
        }

        return $data;
    }

    /**
     * @return array<string,Collection<int,object>>
     */
    private function productIndex(): array
    {
        $query = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('p.is_archived', false)
            ->whereIn('b.name', ['BAXI', 'Viessmann', 'Reflex', 'Flamco'])
            ->select('p.id', 'p.sku', 'p.name', 'p.price', 'p.currency', 'b.name as brand');

        if (! (bool) $this->option('include-inactive')) {
            $query->where('p.is_active', true);
        }

        return $query->get()
            ->groupBy(fn ($product) => $this->keyFor((string) $product->brand, (string) $product->name))
            ->all();
    }

    private function brandFor(array $row): ?string
    {
        $text = mb_strtoupper(implode(' ', [
            $row['sheet'] ?? '',
            $row['section'] ?? '',
            $row['name'] ?? '',
        ]));

        return match (true) {
            str_contains($text, 'BAXI'),
            str_contains($text, 'AMPERA'),
            str_contains($text, 'ECO'),
            str_contains($text, 'LUNA'),
            str_contains($text, 'DUO-TEC'),
            str_contains($text, 'NUVOLA'),
            str_contains($text, 'SLIM') => 'BAXI',
            str_contains($text, 'VIESSMANN'),
            str_contains($text, 'VITODENS'),
            str_contains($text, 'VITOCOM'),
            str_contains($text, 'VITOTRONIC') => 'Viessmann',
            str_contains($text, 'REFLEX') => 'Reflex',
            str_contains($text, 'РАСШИРИТЕЛЬНЫЕ БАКИ') && str_contains($text, 'ЗАКРЫТЫХ СИСТЕМ') => 'Reflex',
            str_contains($text, 'РАСШИРИТЕЛЬНЫЕ БАКИ') && str_contains($text, 'СИСТЕМ ПИТЬЕВОГО') => 'Reflex',
            str_contains($text, 'FLAMCO') => 'Flamco',
            default => null,
        };
    }

    private function keyFor(string $brand, string $name, string $section = ''): string
    {
        $prefix = '';
        $sectionUpper = mb_strtoupper($section);

        if ($brand === 'BAXI' && str_contains($sectionUpper, 'AMPERA')) {
            $prefix = 'AMPERA ';
        }

        if ($brand === 'Reflex') {
            if (str_contains($sectionUpper, 'СИСТЕМ ПИТЬЕВОГО')) {
                $prefix = 'DE ';
            } elseif (str_contains($sectionUpper, 'ЗАКРЫТЫХ')) {
                $prefix = 'NG ';
            }
        }

        $value = mb_strtoupper(html_entity_decode($prefix . ' ' . $name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = str_replace(['Ё', ',', '+'], ['Е', '.', ''], $value);
        if ($brand === 'Reflex') {
            $value = preg_replace('/\b\d+\s*БАРА?\b/u', '', $value) ?? $value;
        }
        $value = preg_replace('/\([^)]*\)/u', '', $value) ?? $value;
        $value = preg_replace(
            '/(ГАЗОВЫЙ|КОТЕЛ|КОТЁЛ|КОНДЕНСАЦИОННЫЙ|ЭЛЕКТРИЧЕСКИЙ|BAXI|VIESSMANN|REFLEX|FLAMCO|МЕМБРАННЫЙ|БАК|ГИДРОАККУМУЛЯТОР|ВОДОНАГРЕВАТЕЛЬ|КОСВЕННЫЙ|НАСТЕННЫЙ|НАПОЛЬНЫЙ|ДЫМОХОДВПОДАРОК|ОДНОКОНТУРНЫЙ|ДВУХКОНТУРНЫЙ|ЛИТРОВ|ЛИТР|БАРА|БАР|НЕОБХОДИМСТАБИЛИЗАТОРТЯГИ|СКРЫШКОЙ|СОСНОВАНИЕМ|СОПЦИОНАЛЬНЫМТЭНОМ|СТАЛЬНОЙКОРПУС|РЕЦИРКУЛЯЦИЯ|ПРЕДОХРАНИТЕЛЬНЫЙКЛАПАН6BARВКОМПЛЕКТЕ|МОЩНОСТЬПРИ80С|КВТ|ММ)/u',
            '',
            $value
        ) ?? $value;
        $value = preg_replace('/[^A-Z0-9А-Я]+/u', '', $value) ?? $value;

        return $brand . '|' . $value;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $value = str_replace(',', '', preg_replace('/[^0-9.,-]/', '', (string) $value) ?? '');

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @param array<int,array<string,string>> $rows
     */
    private function writeExport(string $relativePath, array $rows): void
    {
        $relativePath = $this->normalizeExportPath($relativePath);
        if ($relativePath === '') {
            return;
        }

        $path = public_path($relativePath);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        $headers = ['Код товара', 'Название', 'Цена', 'Валюта', 'Наличие', 'ID KOTLOV', 'Бренд', 'Артикул VISTAR', 'Название VISTAR', 'Лист VISTAR'];
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers), ';');
        }

        fclose($handle);

        $this->info(sprintf('Exported %d matched products to public/%s', count($rows), $relativePath));
    }

    /**
     * @param array<int,array<string,string>> $rows
     */
    private function writeSbgExport(string $relativePath, array $rows): void
    {
        $relativePath = $this->normalizeExportPath($relativePath);
        if ($relativePath === '') {
            return;
        }

        $path = public_path($relativePath);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        $headers = ['ID товара', 'Название', 'Цена', 'Валюта', 'Наличие', 'Ссылка SBG', 'Текущая цена SBG', 'Артикул VISTAR', 'Название VISTAR', 'Лист VISTAR'];
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers), ';');
        }

        fclose($handle);

        $this->info(sprintf('Exported %d existing SBG product updates to public/%s', count($rows), $relativePath));
    }

    private function deleteExport(string $relativePath): void
    {
        $relativePath = $this->normalizeExportPath($relativePath);
        if ($relativePath === '') {
            return;
        }

        $path = public_path($relativePath);
        if (is_file($path)) {
            unlink($path);
            $this->info('Deleted public/' . $relativePath);
        } else {
            $this->info('Nothing to delete at public/' . $relativePath);
        }
    }

    private function normalizeExportPath(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return '';
        }

        return 'exports/' . basename($relativePath);
    }
}
