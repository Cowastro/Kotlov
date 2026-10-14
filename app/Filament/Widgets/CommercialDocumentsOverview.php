<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommercialDocumentsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Коммерческая работа';

    protected ?string $description = 'Текущая воронка и результаты за последние 30 дней.';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $data = app(CommercialDocumentAnalytics::class)->summary();
        $url = CommercialDocumentResource::getUrl('index');
        $topReason = $data['top_loss_reason']
            ? (CommercialDocument::OUTCOME_REASONS[$data['top_loss_reason']] ?? $data['top_loss_reason'])
            : 'Потерь с причиной пока нет';

        return [
            Stat::make('КП в работе', $data['active_count'])
                ->description($this->money($data['active_total']).' в открытых предложениях')
                ->descriptionIcon('heroicon-o-document-text')->color('info')->url($url),
            Stat::make('Требует действия', $data['needs_action_count'])
                ->description($this->money($data['needs_action_total']).' · срок или создание заказа')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($data['needs_action_count'] ? 'warning' : 'success')->url($url),
            Stat::make('Конверсия решений', number_format((float) $data['conversion_percent'], 1, ',', ' ').'%')
                ->description('Согласовано: '.$data['won_count'].' · потеряно: '.$data['lost_count'].' · с '.$data['period_start'])
                ->descriptionIcon('heroicon-o-chart-bar')->color('success')->url($url),
            Stat::make('Неоплаченные счета', $data['unpaid_invoice_count'])
                ->description($this->money($data['unpaid_invoice_total']).' · причина потерь: '.$topReason)
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($data['unpaid_invoice_count'] ? 'danger' : 'success')->url($url),
        ];
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', ' ').' BYN';
    }
}
