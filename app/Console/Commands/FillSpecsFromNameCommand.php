<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pump accessories whose aqualider.by page has no specs table carry them in
 * the name: «Кабель КВВ-п 4*1,5 (бухта 50 м)», «Оголовок скважинный АКВАРОБОТ
 * АОС 152-40 (с) чугун», «Шланг поливочный ROLL TELESCOPE 15 м». Only cards
 * without attribute values are filled, with the same attributes as the
 * aqualider enrichment.
 *
 *   php artisan catalog:fill-specs-from-name --brand=unipump            # dry run
 *   php artisan catalog:fill-specs-from-name --brand=unipump --apply
 */
class FillSpecsFromNameCommand extends Command
{
    use ScrapesAqualiderCard;

    protected $signature = 'catalog:fill-specs-from-name {--brand= : Brand slug} {--apply : Write the specs}';

    protected $description = 'Fill specs of cables, wellheads and hoses from their names';

    public function handle(): int
    {
        $stats = ['cards' => 0, 'parsed' => 0, 'attrs' => 0, 'unknown' => 0];
        $cards = DB::table('products as p')
            ->leftJoin('product_attribute_values as pav', 'pav.product_id', '=', 'p.id')
            ->whereNull('pav.id')
            ->where('p.is_archived', false)
            ->when($this->option('brand'), fn ($q) => $q->whereIn('p.brand_id', DB::table('brands')->where('slug', $this->option('brand'))->select('id')))
            ->get(['p.id', 'p.name', 'p.slug', 'p.category_id']);

        foreach ($cards as $card) {
            $stats['cards']++;
            $specs = $this->specs((string) $card->name);
            if ($specs === []) {
                $stats['unknown']++;
                $this->line("audit.name_specs.unknown={$card->id}:{$card->slug}");

                continue;
            }
            $stats['parsed']++;
            $this->line("audit.name_specs.card={$card->id}:{$card->slug}:".count($specs));
            if ($this->option('apply')) {
                $stats['attrs'] += $this->writeCardSpecs((int) $card->id, (int) $card->category_id, $specs);
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.name_specs.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, string> attribute «name, unit» => value */
    public function specs(string $name): array
    {
        // «3х1,5» / «3×1,5» → «3*1,5», only between digits («бухта» keeps its «х»).
        $n = preg_replace('/(\d)\s*[×хХxX]\s*(\d)/u', '$1*$2', $name) ?? $name;
        if (preg_match('/кабель\s+(квв(?:-[пт])?)\s+(\d+)\s*\*\s*(\d+(?:[.,]\d+)?)/iu', $n, $m)) {
            $specs = [
                'Марка кабеля' => mb_strtoupper($m[1]),
                'Тип кабеля' => match (mb_strtolower($m[1])) { 'квв-п' => 'Плоский', default => 'Круглый' },
                'Количество жил, шт' => $m[2],
                'Сечение жилы, мм²' => str_replace(',', '.', $m[3]),
            ];
            if (preg_match('/бухта\s*(\d+)\s*м/iu', $n, $b)) {
                $specs['Длина бухты, м'] = $b[1];
            }

            return $specs;
        }
        // «АОС» may be typed with Latin letters, «133-40» also as «133/40» or «133*40».
        if (preg_match('/оголовок.*?[аa][оo][сc]\s*-?\s*(\d{2,3})\s*[-\/*]\s*(\d{2,3})/iu', $n, $m)) {
            // The material is written only when the name states it.
            return array_filter([
                'Диаметр обсадной трубы, мм' => $m[1],
                'Диаметр водоподъёмной трубы, мм' => $m[2],
                'Материал корпуса' => preg_match('/чугун/iu', $n) ? 'Чугун' : null,
            ]);
        }
        if (preg_match('/шланг.*?(telescope|телескоп).*?(\d+)\s*м/iu', $n, $m)) {
            return [
                'Тип шланга' => 'Растягивающийся (телескопический)',
                'Длина, м' => $m[2],
            ];
        }

        return [];
    }
}
