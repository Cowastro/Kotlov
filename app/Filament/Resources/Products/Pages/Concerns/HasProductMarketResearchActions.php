<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Filament\Resources\MarketResearchReports\MarketResearchReportResource;
use App\Services\CommercialDocuments\CommercialOfferCopilotService;
use App\Services\Market\MarketWebResearchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasProductMarketResearchActions
{
    /** @return array<Action> */
    protected function productMarketResearchActions(): array
    {
        return [
            Action::make('research_market')
                ->label('Исследовать рынок')
                ->icon('heroicon-o-globe-alt')
                ->color('info')
                ->slideOver()
                ->modalWidth('7xl')
                ->modalHeading(fn (): string => 'Исследование рынка · '.$this->record->name)
                ->modalDescription('Если исследование уже сохранено, откроется оно. Если нет — система выполнит поиск и прикрепит результат к карточке товара.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Закрыть')
                ->modalContent(fn () => view('filament.market.live-price-research', [
                    'research' => app(MarketWebResearchService::class)->research(
                        $this->record,
                        (float) $this->record->price,
                    ),
                    'pricing' => app(CommercialOfferCopilotService::class)->pricing(
                        $this->record->id,
                        (float) $this->record->price,
                    ),
                ])),
            Action::make('refresh_market_research')
                ->label('Обновить исследование')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Выполнить новое исследование?')
                ->modalDescription('Система заново проверит интернет-источники и сохранит новый результат в истории товара.')
                ->action(function (): void {
                    $research = app(MarketWebResearchService::class)->research(
                        $this->record,
                        (float) $this->record->price,
                        forceRefresh: true,
                    );

                    Notification::make()
                        ->title(($research['status'] ?? null) === 'ready'
                            ? 'Исследование обновлено и сохранено'
                            : 'Не удалось обновить исследование')
                        ->body((string) (($research['status'] ?? null) === 'ready'
                            ? 'Результат прикреплён к карточке товара.'
                            : ($research['message'] ?? 'Провайдер не вернул готовый отчёт.')))
                        ->color(($research['status'] ?? null) === 'ready' ? 'success' : 'danger')
                        ->send();
                }),
            Action::make('market_research_history')
                ->label('История исследований')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn (): bool => $this->record->marketResearchReports()->exists())
                ->url(fn (): string => MarketResearchReportResource::getUrl('index', [
                    'filters' => ['product_id' => ['value' => $this->record->id]],
                ])),
        ];
    }
}
