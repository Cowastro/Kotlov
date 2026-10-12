<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResolveSbgAmbiguousBatchCommand extends Command
{
    protected $signature = 'integration:resolve-sbg-ambiguous
        {--batch=exact-20261012-a : Curated resolution batch}
        {--code=* : Limit the run to one or more 1C codes}
        {--apply : Persist the verified links; default is a dry run}';

    protected $description = 'Safely link a curated batch of exact SanBusinessGroup 1C matches.';

    private const SOURCE_NAME = '1С СанБизнесГруп';

    private const PARTNER_NAME = 'ООО «СанБизнесГруп»';

    /**
     * Each row was manually reviewed against the production ambiguous queue.
     * The command still refuses to apply it unless both names and the current
     * candidate set keep confirming the same exact product identity.
     *
     * @var array<string, array{sku:string,item:list<string>,product:list<string>}>
     */
    private const BATCH_EXACT_20261012_A = [
        'УТ-00000567' => ['sku' => 'KOTLOV-007963', 'item' => ['Отвод 45', '430-0.5', 'D80', '2S'], 'product' => ['Отвод 45', '430-0.5', 'D80', '2S']],
        'УТ-00000561' => ['sku' => 'KOTLOV-007972', 'item' => ['Отвод 45', '430-0.5', 'D100', '2S'], 'product' => ['Отвод 45', '430-0.5', 'D100', '2S']],
        'УТ-00000346' => ['sku' => 'PS-009.016', 'item' => ['Конденсатоотвод', '430-0.5', 'D120'], 'product' => ['Конденсато', '430', '0,5', '120']],
        'УТ-00000345' => ['sku' => 'KOTLOV-007983', 'item' => ['Конденсатоотвод', '430-0.5', 'D110'], 'product' => ['Конденсатоотвод', '430-0.5', 'D110']],
        'УТ-00000344' => ['sku' => 'KOTLOV-007975', 'item' => ['Конденсатоотвод', '430-0.5', 'D100'], 'product' => ['Конденсатоотвод', '430-0.5', 'D100']],
        'УТ-00000318' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2сек'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000032' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2 сек'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000005' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2ceк'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000211' => ['sku' => 'PS-009.002', 'item' => ['Заглушка ревизии', '430-0.5', 'D150'], 'product' => ['Заглушка ревизии', '430', '0,5', '150']],
        'УТ-00000207' => ['sku' => 'KOTLOV-007982', 'item' => ['Заглушка ревизии', '430-0.5', 'D110'], 'product' => ['Заглушка ревизии', '430-0.5', 'D110']],
        'УТ-00001518' => ['sku' => 'KOTLOV-007974', 'item' => ['Заглушка ревизии', '430-0.5', 'D100'], 'product' => ['Заглушка ревизии', '430-0.5', 'D100']],
        'УТ-00001517' => ['sku' => 'KOTLOV-007966', 'item' => ['Конденсатоотвод', '430-0.5', 'D80'], 'product' => ['Конденсатоотвод', '430-0.5', 'D80']],
        'УТ-00002026' => ['sku' => 'KOTLOV-007965', 'item' => ['Заглушка ревизии', '430-0.5', 'D80'], 'product' => ['Заглушка ревизии', '430-0.5', 'D80']],
        'УТ-00008135' => ['sku' => 'PS-011.149', 'item' => ['Дверка ВЕЗУВИЙ', '223', 'Бронза'], 'product' => ['Дверка ВЕЗУВИЙ', '223', 'Бронза']],
        'УТ-00006301' => ['sku' => 'PS-011.839', 'item' => ['Мангал', 'Поход 1'], 'product' => ['Мангал', 'Пегас', 'Поход 1']],
        'УТ-00006157' => ['sku' => 'PS-011.852', 'item' => ['Обрезь базальта', '3кг'], 'product' => ['Обрезь базальта', '3кг']],
        'УТ-00005590' => ['sku' => 'PS-009.930', 'item' => ['Буржуйка мини', '8 мм'], 'product' => ['Буржуйка мини', '8 мм']],
        'УТ-00005218' => ['sku' => 'PS-009.931', 'item' => ['Буржуйка с конфоркой', '8 мм'], 'product' => ['Буржуйка с конфоркой', '8 мм']],
        'УТ-00004236' => ['sku' => 'PS-009.929', 'item' => ['Буржуйка мини с конфоркой', '8мм'], 'product' => ['Буржуйка мини с комфоркой', '8 мм']],
        'УТ-00003017' => ['sku' => 'PS-008.924', 'item' => ['Гидравлический разделитель', 'Г-50/2'], 'product' => ['Гидравлический разделитель', 'Г-50/2']],
        'УТ-00002857' => ['sku' => 'PS-007.059', 'item' => ['Счетчик газа', 'СГБ', '1,8', 'Элехант'], 'product' => ['Счетчик газа', 'Элехант', 'СГБ-1,8']],
        'УТ-00009513' => ['sku' => 'KOTLOV-022421', 'item' => ['КПД', 'Отвод 45', '2мм', 'ф120'], 'product' => ['КПД', 'Отвод 45', '2мм', 'ф120']],
        'УТ-00009473' => ['sku' => 'KOTLOV-022428', 'item' => ['КПД', 'Отвод 90', '2мм', 'ф120', 'ревизией'], 'product' => ['КПД', 'Отвод 90', '2мм', 'ф120', 'ревизией']],
        'УТ-00009461' => ['sku' => 'KOTLOV-022412', 'item' => ['КПД', 'Труба 500мм', '2мм', 'ф120'], 'product' => ['КПД', 'Труба 500мм', '2мм', 'ф120']],
    ];

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        if ($this->option('batch') !== 'exact-20261012-a') {
            $this->error('Unknown batch.');

            return self::FAILURE;
        }

        $rows = self::BATCH_EXACT_20261012_A;
        $codes = array_values(array_filter(array_map('strval', (array) $this->option('code'))));
        if ($codes !== []) {
            $rows = array_intersect_key($rows, array_flip($codes));
        }

        $apply = (bool) $this->option('apply');
        $stats = array_fill_keys(['configured', 'ready', 'applied', 'already', 'missing_item', 'missing_product', 'wrong_source', 'unsafe_name', 'not_candidate', 'conflict'], 0);
        $stats['configured'] = count($rows);
        $details = [];

        foreach ($rows as $code => $rule) {
            $items = IntegrationProduct::query()->with(['source', 'product'])->where('external_code', $code)->get();
            if ($items->count() !== 1) {
                $stats['missing_item']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'expected exactly one 1C row, found '.$items->count()];

                continue;
            }

            $item = $items->first();
            $product = Product::query()->where('sku', $rule['sku'])->first();
            if (! $product) {
                $stats['missing_product']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'site product not found'];

                continue;
            }

            if ($item->source?->name !== self::SOURCE_NAME || $item->source?->partnerName() !== self::PARTNER_NAME) {
                $stats['wrong_source']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'unexpected supplier/source'];

                continue;
            }

            if (! $this->containsAll($item->name, $rule['item']) || ! $this->containsAll($product->name, $rule['product'])) {
                $stats['unsafe_name']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'identity guard no longer matches'];

                continue;
            }

            if ($item->product_id) {
                if ((int) $item->product_id === (int) $product->id && $item->match_status === 'matched') {
                    $stats['already']++;
                    $details[] = [$code, $rule['sku'], 'already', Str::limit($product->name, 64)];
                } else {
                    $stats['conflict']++;
                    $details[] = [$code, $rule['sku'], 'blocked', 'already linked to another card'];
                }

                continue;
            }

            if ($item->match_status !== 'ambiguous' || (float) $item->stock_quantity <= 0) {
                $stats['conflict']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'row is no longer an in-stock ambiguous item'];

                continue;
            }

            $candidateIds = collect($item->candidates)->pluck('product_id')->map(fn ($id): int => (int) $id);
            if (! $candidateIds->contains((int) $product->id)) {
                $stats['not_candidate']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'product is no longer a current candidate'];

                continue;
            }

            $stats['ready']++;
            if ($apply) {
                $linked = $decision->confirmCandidate($item, (int) $product->id);
                if (! $linked) {
                    $stats['conflict']++;
                    $details[] = [$code, $rule['sku'], 'blocked', 'candidate changed during apply'];

                    continue;
                }
                $stats['applied']++;
            }
            $details[] = [$code, $rule['sku'], $apply ? 'linked' : 'ready', Str::limit($product->name, 64)];
        }

        $this->line($apply ? 'APPLY: verified links were persisted.' : 'DRY RUN: no database changes.');
        $this->table(['1C code', 'site SKU', 'status', 'detail'], $details);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        $blocked = $stats['missing_item'] + $stats['missing_product'] + $stats['wrong_source']
            + $stats['unsafe_name'] + $stats['not_candidate'] + $stats['conflict'];

        return $blocked === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<string> $fragments */
    private function containsAll(string $value, array $fragments): bool
    {
        $value = mb_strtolower(str_replace([',', '–', '—'], ['.', '-', '-'], $value));

        return collect($fragments)->every(function (string $fragment) use ($value): bool {
            $fragment = mb_strtolower(str_replace([',', '–', '—'], ['.', '-', '-'], $fragment));

            return str_contains($value, $fragment);
        });
    }
}
