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
        {--include-inactive : Allow inactive products to be matched}';

    protected $description = 'Sync safe retail prices from VISTAR Price 01.09.2026.';

    private const DATA_FILE = 'database/data/vistar-price-2026-09-01.json';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $data = $this->loadData();

        if ($data === null) {
            return self::FAILURE;
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

        return self::SUCCESS;
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
            ->select('p.id', 'p.name', 'p.price', 'b.name as brand');

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
}
