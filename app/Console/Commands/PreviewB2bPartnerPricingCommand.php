<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Pricing\B2bPartnerPricingEngine;
use Illuminate\Console\Command;

class PreviewB2bPartnerPricingCommand extends Command
{
    protected $signature = 'b2b:preview-partner-pricing
        {supplier=euroterm : Код поставщика}
        {--active-only : Не учитывать черновые правила}';

    protected $description = 'Предпросмотр B2B-цен и причин блокировки без публикации клиентам.';

    public function handle(B2bPartnerPricingEngine $engine): int
    {
        $supplier = Supplier::query()->where('code', (string) $this->argument('supplier'))->first();
        if (! $supplier) {
            $this->error('Поставщик не найден.');

            return self::FAILURE;
        }

        $preview = $engine->preview($supplier, ! $this->option('active-only'));
        $this->line('metric.b2b_preview.total='.$preview['total']);
        $this->line('metric.b2b_preview.would_publish='.$preview['would_publish']);
        $this->line('metric.b2b_preview.published='.$preview['published']);

        foreach ($preview['reasons'] as $reason => $count) {
            $this->line("metric.b2b_preview.{$reason}={$count}");
        }

        $this->warn('Это только предпросмотр. Цены клиентам не опубликованы.');

        return self::SUCCESS;
    }
}
