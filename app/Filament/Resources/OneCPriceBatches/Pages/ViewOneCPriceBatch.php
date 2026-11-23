<?php

namespace App\Filament\Resources\OneCPriceBatches\Pages;

use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCPriceBatches;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewOneCPriceBatch extends ViewRecord
{
    protected static string $resource = OneCPriceBatchResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Пакет цен №'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Отправить в 1С')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === OneCPriceBatch::DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Отправить цены в 1С?')
                ->modalDescription(fn (): string => 'Агент на сервере 1С в течение 5 минут создаст «Установку цен номенклатуры» на '
                    .$this->record->items()->count().' товаров (4 вида цен на товар). Документ не проводится: бухгалтер проверит и проведёт его в 1С.')
                ->schema(fn (): array => app(OneCPriceBatches::class)->belowCost($this->record) > 0 ? [
                    Toggle::make('below_cost')->label('У '.app(OneCPriceBatches::class)->belowCost($this->record).' товаров цена ниже себестоимости 1С — всё равно отправить')->required()->accepted(),
                ] : [])
                ->action(fn (array $data) => $this->run(fn () => app(OneCPriceBatches::class)->approve($this->record, auth()->user(), (bool) ($data['below_cost'] ?? false)), 'Пакет передан агенту 1С')),
            Action::make('retry')
                ->label('Отправить ещё раз')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->record->status === OneCPriceBatch::FAILED)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => app(OneCPriceBatches::class)->retry($this->record), 'Пакет снова в очереди агента')),
            Action::make('toDraft')
                ->label('Вернуть в черновик')
                ->icon('heroicon-o-pencil')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status, [OneCPriceBatch::FAILED, OneCPriceBatch::APPROVED], true))
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => app(OneCPriceBatches::class)->retry($this->record, toDraft: true), 'Пакет снова можно править')),
            DeleteAction::make()->label('Удалить черновик')
                ->visible(fn (): bool => $this->record->status === OneCPriceBatch::DRAFT),
        ];
    }

    private function run(callable $action, string $title): void
    {
        try {
            $action();
            $this->record->refresh();
            Notification::make()->success()->title($title)->send();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Не получилось')->body($e->getMessage())->send();
        }
    }
}
