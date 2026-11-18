<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Support\TeplovSukhovName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renames Теплов и Сухов cards to one scheme (App\Support\TeplovSukhovName).
 *
 *   php artisan catalog:normalize-teplov-names                 # dry run: public/exports/tis-names.xlsx «сейчас → станет»
 *   php artisan catalog:normalize-teplov-names --apply         # rename, old names kept in storage/app/catalog/
 *   php artisan catalog:normalize-teplov-names --restore=FILE  # put the names of that backup back
 *
 * Left as is: names the scheme does not recognise, two cards that would get one name (likely duplicates),
 * and a card whose link to the ТиС price list row would no longer pass the check of supplier:sync-teplov-sukhov-retail.
 * Only the name changes (and h1 when it repeated the name); the slug, and so the page address, stays.
 */
class NormalizeTeplovNamesCommand extends Command
{
    protected $signature = 'catalog:normalize-teplov-names
        {--apply : Rename the cards}
        {--export=tis-names : File name of the review table under public/exports}
        {--restore= : Backup file name in storage/app/catalog to put the old names back}';

    protected $description = 'Rename Теплов и Сухов chimney cards to one naming scheme';

    public function handle(): int
    {
        if (filled($this->option('restore'))) {
            return $this->restore((string) $this->option('restore'));
        }

        $brand = Brand::query()->where('name', TeplovSukhovName::BRAND)->first();
        if (! $brand) {
            $this->error('Бренд «Теплов и Сухов» не найден.');

            return self::FAILURE;
        }
        // Archived cards (merged duplicates among them) keep their names and do not count as taken.
        $cards = Product::query()->with('category:id,name')->where('brand_id', $brand->id)->where('is_archived', false)->orderBy('id')->get(['id', 'name', 'h1', 'slug', 'category_id']);
        $links = SupplierProduct::query()->whereIn('product_id', $cards->pluck('id'))
            ->whereHas('supplier', fn ($supplier) => $supplier->where('code', 'teplov'))
            ->where('match_confidence', '!=', 'manual_link')->whereNotNull('supplier_name')
            ->get(['product_id', 'supplier_name'])->groupBy('product_id');
        $matcher = app(SyncTeplovSukhovRetailPricesCommand::class);

        $plan = $cards->map(fn (Product $card) => ['card' => $card, 'new' => TeplovSukhovName::normalize((string) $card->name)]);
        $taken = $plan->pluck('new')->filter()->countBy();
        $current = $cards->pluck('id', 'name');
        $plan = $plan->map(function (array $row) use ($taken, $current, $links, $matcher): array {
            $card = $row['card'];
            $row['status'] = match (true) {
                $row['new'] === null => 'not_recognised',
                $row['new'] === $card->name => 'same',
                $taken[$row['new']] > 1 || (isset($current[$row['new']]) && $current[$row['new']] !== $card->id) => 'possible_duplicate',
                collect($links->get($card->id, []))->contains(fn ($link) => $matcher->cardNameMatches((string) $card->name, (string) $link->supplier_name)
                    && ! $matcher->cardNameMatches((string) $row['new'], (string) $link->supplier_name)) => 'keeps_price_link',
                default => 'rename',
            };

            return $row;
        });

        $this->export($plan->all(), Str::slug((string) $this->option('export')) ?: 'tis-names');
        foreach ($plan->countBy('status') as $status => $count) {
            $this->line("metric.teplov_names.{$status}={$count}");
        }

        $renames = $plan->where('status', 'rename');
        if (! $this->option('apply')) {
            $this->info('Проверка завершена. Названия не изменялись.');

            return self::SUCCESS;
        }

        $backup = 'catalog/tis-names-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($backup, json_encode($renames->mapWithKeys(fn (array $row) => [$row['card']->id => ['name' => $row['card']->name, 'h1' => $row['card']->h1]])->all(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->line('audit.teplov_names.backup='.basename($backup));
        DB::transaction(function () use ($renames): void {
            foreach ($renames as $row) {
                $card = $row['card'];
                $values = ['name' => $row['new']];
                if ((string) $card->h1 !== '' && $card->h1 === $card->name) {
                    $values['h1'] = $row['new'];
                }
                $card->update($values);
            }
        });
        $this->line('metric.teplov_names.updated='.$renames->count());

        return self::SUCCESS;
    }

    /** @param list<array{card: Product, new: ?string, status: string}> $plan */
    private function export(array $plan, string $name): void
    {
        $labels = ['rename' => 'переименовать', 'same' => 'уже по схеме', 'not_recognised' => 'оставить: не по схеме',
            'possible_duplicate' => 'оставить: похоже на дубль', 'keeps_price_link' => 'оставить: иначе слетит привязка к прайсу'];
        usort($plan, fn ($a, $b) => [$a['status'] !== 'rename', (string) $a['card']->category?->name, (string) ($a['new'] ?? $a['card']->name)]
            <=> [$b['status'] !== 'rename', (string) $b['card']->category?->name, (string) ($b['new'] ?? $b['card']->name)]);
        $sheet = (new Spreadsheet)->getActiveSheet()->setTitle('Названия ТиС');
        $sheet->fromArray([['ID', 'Раздел', 'Сейчас', 'Станет', 'Что делаем']]);
        $line = 2;
        foreach ($plan as $row) {
            $sheet->fromArray([[$row['card']->id, (string) $row['card']->category?->name, $row['card']->name, $row['new'] ?? '', $labels[$row['status']]]], null, 'A'.$line++);
        }
        foreach (['A' => 8, 'B' => 28, 'C' => 70, 'D' => 70, 'E' => 34] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->setAutoFilter('A1:E'.($line - 1));
        $sheet->freezePane('A2');
        $dir = public_path('exports');
        is_dir($dir) || mkdir($dir, 0755, true);
        (new Xlsx($sheet->getParent()))->save($dir.'/'.$name.'.xlsx');
        $this->line('audit.teplov_names.export=exports/'.$name.'.xlsx');
    }

    private function restore(string $file): int
    {
        $path = 'catalog/'.basename($file);
        $names = Storage::disk('local')->exists($path) ? json_decode(Storage::disk('local')->get($path), true) : null;
        if (! is_array($names)) {
            $this->error('Резервная копия не найдена.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($names): void {
            foreach ($names as $id => $old) {
                Product::query()->whereKey((int) $id)->update(['name' => $old['name'], 'h1' => $old['h1']]);
            }
        });
        $this->line('metric.teplov_names.restored='.count($names));

        return self::SUCCESS;
    }
}
