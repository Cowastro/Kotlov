<?php

namespace App\Console\Commands;

use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\OneCItemLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RepairOneC2465LinkCommand extends Command
{
    public const KEY = '3f5789b5-d537-11e9-a218-1c1b0d1de7c2';

    protected $signature = 'onec:repair-2465-link {--apply : Снять проверенную лишнюю общую связь}';

    protected $description = 'Проверить и устранить старую двойную привязку УТ-00002465 без удаления данных';

    public function handle(OneCItemLinks $links): int
    {
        try {
            return DB::transaction(function () use ($links): int {
                $item = OneCItem::where('ref_key', self::KEY)->lockForUpdate()->firstOrFail();
                $rows = $links->query(self::KEY)->lockForUpdate()->get();
                $expected = [
                    '3f5789b6' => 'KOTLOV-030290', '3f5789b7' => 'KOTLOV-030292',
                    '3f5789b8' => 'KOTLOV-030291', '3f5789b9' => 'KOTLOV-030293',
                    '3f5789ba' => 'KOTLOV-030294', '3f5789bb' => 'KOTLOV-030295',
                ];
                if ($item->code !== 'УТ-00002465' || $rows->count() !== 7) {
                    throw new RuntimeException('Состав записей изменился — требуется новая проверка.');
                }
                foreach ($expected as $prefix => $sku) {
                    $variant = $rows->where('external_id', self::KEY.'#'.$prefix.'-d537-11e9-a218-1c1b0d1de7c2')->sole();
                    if ($variant->match_status !== 'matched' || $variant->product?->sku !== $sku || $variant->product->is_archived) {
                        throw new RuntimeException('Связь характеристики изменилась: '.$sku);
                    }
                }
                $base = $rows->where('external_id', self::KEY)->sole();
                if ($base->product_id === null && $base->match_status === 'ignored') {
                    $this->info('Уже исправлено: общая запись отключена, шесть характеристик сохранены.');

                    return self::SUCCESS;
                }
                if ($base->match_status !== 'matched' || $base->product?->sku !== 'KOTLOV-030290') {
                    throw new RuntimeException('Общая связь отличается от проверенной — изменения отменены.');
                }
                $this->info('УТ-00002465: общая запись дублирует красную характеристику; остальные пять связей отдельные.');
                if (! $this->option('apply')) {
                    $this->info('Проверка без изменений. Для применения: --apply');

                    return self::SUCCESS;
                }
                $backup = 'onec-link-repairs/2465-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                if (! Storage::disk('local')->put($backup, json_encode(['item' => $item->getAttributes(), 'rows' => $rows->map->getAttributes()->all()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    throw new RuntimeException('Резервная копия не сохранена — изменения отменены.');
                }
                // Retain the exchange row and all financial/stock fields. Ignored prevents automatic rematching.
                $base->forceFill(['product_id' => null, 'match_status' => 'ignored', 'match_method' => 'manual_onec_unlink',
                    'match_confidence' => null, 'matched_at' => null])->save();
                $links->refresh(self::KEY);
                $summary = $item->fresh();
                if ($summary->link_conflict || $summary->site_link_count !== 6 || $summary->characteristic_count !== 6) {
                    throw new RuntimeException('Проверка результата не пройдена — изменения отменены.');
                }
                $this->info('Исправлено: 6/6, конфликт снят. Цены, остатки и карточки сохранены. Копия: '.$backup);

                return self::SUCCESS;
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
