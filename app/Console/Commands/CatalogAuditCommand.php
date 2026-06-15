<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only аудит «здоровья» каталога Marketplace (Catalog Intelligence, этап 1).
 *
 * Команда НИЧЕГО не меняет в базе — только считает и выводит отчёт.
 *
 * Живой каталог определяется строго как: is_active=1 AND is_archived=0 AND price>0.
 * Метрика контроля = живые товары со связью supplier_products / все живые товары.
 * Архивные связки НЕ считаются контролем живого каталога.
 *
 * Актуальность поставщика берётся ТОЛЬКО из supplier_products.last_synced_at.
 * Поле supplier_syncs.last_status намеренно не используется — оно недостоверно
 * (показывает never у поставщиков со свежими данными).
 *
 * Использование:
 *   php artisan catalog:audit
 *   php artisan catalog:audit --stale-days=7      # порог «протухшей» синхронизации
 *   php artisan catalog:audit --plan              # показать планируемую схему product_audit_flags
 */
class CatalogAuditCommand extends Command
{
    protected $signature = 'catalog:audit
        {--stale-days=7 : Порог в днях, после которого синхронизация поставщика считается устаревшей}
        {--plan : Показать планируемую структуру таблицы product_audit_flags (без создания)}';

    protected $description = 'Read-only аудит каталога: покрытие поставщиками, контролируемость живого каталога, аномалии';

    public function handle(): int
    {
        $this->line('<fg=cyan;options=bold>Catalog Intelligence — аудит каталога (read-only)</>');
        $this->line('Живой каталог = is_active=1 AND is_archived=0 AND price>0');
        $this->newLine();

        $metrics = $this->collectMetrics();

        $this->renderSummary($metrics);
        $this->newLine();
        $this->renderSupplierFreshness((int) $this->option('stale-days'));

        if ($this->option('plan')) {
            $this->newLine();
            $this->renderPlannedSchema();
        }

        return self::SUCCESS;
    }

    /**
     * Собрать все метрики каталога одним проходом read-only запросов.
     *
     * @return array<string,int|float>
     */
    private function collectMetrics(): array
    {
        $products = fn () => DB::table('products');

        // Идентификаторы товаров, у которых есть привязка к поставщику.
        $linkedIds = DB::table('supplier_products')
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->all();

        $live = fn () => $products()
            ->where('is_active', 1)
            ->where('is_archived', 0)
            ->where('price', '>', 0);

        $total        = $products()->count();
        $liveTotal    = $live()->count();
        $archivedTotal = $products()->where('is_archived', 1)->count();

        // Связи: всего distinct, на живые товары, на архивные товары.
        $linkedDistinct = count($linkedIds);
        $linkedLive     = empty($linkedIds) ? 0 : (clone $live())->whereIn('id', $linkedIds)->count();
        $linkedArchived = empty($linkedIds)
            ? 0
            : $products()->whereIn('id', $linkedIds)->where('is_archived', 1)->count();

        // Живые без связи = живые − живые со связью (не считаем архивные связки).
        $unlinkedLive = $liveTotal - $linkedLive;

        $zeroPrice = $products()
            ->where(fn ($q) => $q->where('price', 0)->orWhereNull('price'))
            ->count();

        $inStock = $products()->where('in_stock', 1)->count();

        $coverage = $liveTotal > 0 ? round($linkedLive / $liveTotal * 100, 1) : 0.0;

        return [
            'total'           => $total,
            'live'            => $liveTotal,
            'archived'        => $archivedTotal,
            'linked_distinct' => $linkedDistinct,
            'linked_live'     => $linkedLive,
            'linked_archived' => $linkedArchived,
            'unlinked_live'   => $unlinkedLive,
            'zero_price'      => $zeroPrice,
            'in_stock'        => $inStock,
            'coverage'        => $coverage,
        ];
    }

    /**
     * @param array<string,int|float> $m
     */
    private function renderSummary(array $m): void
    {
        $pct = fn (int $value): string => $m['live'] > 0
            ? str_pad(round($value / $m['live'] * 100, 1) . '%', 7, ' ', STR_PAD_LEFT)
            : '   —  ';

        $this->table(
            ['Метрика', 'Значение', '% от живого'],
            [
                ['Total products',  number_format($m['total'], 0, '.', ' '),    ''],
                ['Live catalog',    number_format($m['live'], 0, '.', ' '),     '100.0%'],
                ['Archived',        number_format($m['archived'], 0, '.', ' '), ''],
                ['— — —', '', ''],
                ['Linked & live',   number_format($m['linked_live'], 0, '.', ' '),   $pct((int) $m['linked_live'])],
                ['Unlinked live',   number_format($m['unlinked_live'], 0, '.', ' '), $pct((int) $m['unlinked_live'])],
                ['Archived linked', number_format($m['linked_archived'], 0, '.', ' '), ''],
                ['Linked distinct (total)', number_format($m['linked_distinct'], 0, '.', ' '), ''],
                ['— — —', '', ''],
                ['Zero price',      number_format($m['zero_price'], 0, '.', ' '), ''],
                ['In stock',        number_format($m['in_stock'], 0, '.', ' '),  ''],
            ]
        );

        $color = $m['coverage'] >= 50 ? 'green' : ($m['coverage'] >= 25 ? 'yellow' : 'red');
        $this->line(sprintf(
            '  <options=bold>Coverage: <fg=%s>%s%%</></>  (linked_live %d / live %d)',
            $color,
            $m['coverage'],
            $m['linked_live'],
            $m['live']
        ));

        if ($m['linked_distinct'] > 0) {
            $wasted = round($m['linked_archived'] / $m['linked_distinct'] * 100, 1);
            $this->line(sprintf(
                '  <fg=gray>Из %d связок %d (%s%%) ведут на архивные товары и не дают контроля живого каталога.</>',
                $m['linked_distinct'],
                $m['linked_archived'],
                $wasted
            ));
        }
    }

    /**
     * Свежесть синхронизации по поставщикам — строго по supplier_products.last_synced_at.
     */
    private function renderSupplierFreshness(int $staleDays): void
    {
        $threshold = now()->subDays(max(0, $staleDays));

        $rows = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->selectRaw('s.code')
            ->selectRaw('COUNT(*) as links')
            ->selectRaw('SUM(sp.product_id IS NOT NULL) as matched')
            ->selectRaw('MAX(sp.last_synced_at) as price_synced')
            ->selectRaw('MAX(sp.last_stock_synced_at) as stock_synced')
            ->groupBy('s.code')
            ->orderByDesc('links')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  <fg=gray>Связей supplier_products нет.</>');
            return;
        }

        $this->line('<options=bold>Свежесть поставщиков (по supplier_products.last_synced_at):</>');

        $this->table(
            ['Поставщик', 'Связок', 'Сматчено', 'Цена синк', 'Остатки синк', 'Статус'],
            $rows->map(function ($r) use ($threshold) {
                $priceSynced = $r->price_synced ? \Illuminate\Support\Carbon::parse($r->price_synced) : null;
                $isStale = $priceSynced === null || $priceSynced->lt($threshold);

                return [
                    $r->code,
                    (string) $r->links,
                    (string) $r->matched,
                    $priceSynced?->format('d.m.Y H:i') ?? 'never',
                    $r->stock_synced ? \Illuminate\Support\Carbon::parse($r->stock_synced)->format('d.m.Y H:i') : 'never',
                    $isStale ? '⚠ stale' : 'ok',
                ];
            })->toArray()
        );
        $this->line(sprintf('  <fg=gray>Порог устаревания: %d дн. Источник: supplier_products.last_synced_at (supplier_syncs.last_status игнорируется).</>', $staleDays));
    }

    /**
     * Планируемая структура для следующего этапа.
     * НИЧЕГО не создаёт — только печатает схему для согласования.
     */
    private function renderPlannedSchema(): void
    {
        $this->line('<fg=cyan;options=bold>Планируемая таблица product_audit_flags (этап 2 — здесь НЕ создаётся):</>');
        $this->line(<<<'SCHEMA'

  Schema::create('product_audit_flags', function (Blueprint $table) {
      $table->id();
      $table->foreignId('product_id')->constrained()->cascadeOnDelete();

      // Сегмент контролируемости на момент проверки:
      // controlled        — живой + есть свежая связь supplier_products
      // mappable_offline   — живой, связи нет, но матчится по sku/артикулу/названию
      // needs_external     — живой, связи нет, офлайн-матч не дал результата → Serper/DataForSEO
      // archived_linked    — связь есть, но товар в архиве (контролем не считается)
      $table->string('segment', 32)->index();

      // Конкретная причина/аномалия: zero_price | no_brand | no_attributes |
      // no_images | stale_supplier_sync | duplicate_slug | price_old_le_price ...
      $table->string('reason', 64)->nullable()->index();

      $table->unsignedTinyInteger('score')->nullable(); // 0..100 приоритет на доработку
      $table->json('context')->nullable();              // снимок: supplier_code, last_synced_at, candidates
      $table->timestamp('checked_at')->index();
      $table->timestamps();

      $table->unique(['product_id', 'reason']);
  });

SCHEMA);
        $this->line('  <fg=gray>Заполняется будущей записывающей командой (например catalog:audit --persist) после согласования схемы.</>');
    }
}
