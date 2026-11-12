<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\Orders\OrderDealControl;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

class ManageOrderDealAction
{
    public static function make(): Action
    {
        return Action::make('manageDeal')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->label('Этап сделки и контроль')
            ->icon('heroicon-o-adjustments-horizontal')
            ->modalHeading('Этап сделки и следующий шаг')
            ->modalDescription('Переход на исполнение, передачу или завершение выполняет соответствующее действие заказа. Укажите ответственного и следующий шаг. Отложенный заказ вернётся на контроль в указанное время.')
            ->modalSubmitActionLabel('Сохранить')
            ->modalWidth('lg')
            ->visible(fn (Order $record): bool => $record->dealIsActive())
            ->fillForm(fn (Order $record, array $arguments): array => [
                'stage' => $arguments['stage'] ?? $record->effectiveDealStage(),
                'next_action' => $record->deal_next_action,
                'control_at' => $record->deal_control_at,
                'on_hold' => (bool) $record->deal_on_hold,
                'manager_id' => $record->manager_id ?: Order::managerIdFor($record->assigned_to) ?: auth()->id(),
                'version' => (int) $record->deal_version,
                'status' => $record->status,
                'expected_manager_id' => $record->manager_id,
                'expected_assigned_to' => $record->assigned_to,
                'comment' => null,
            ])
            ->schema([
                Hidden::make('version')->required(),
                Hidden::make('status')->required(),
                Hidden::make('expected_manager_id'),
                Hidden::make('expected_assigned_to'),
                Select::make('stage')->label('Этап сделки')->options(Order::DEAL_STAGES)
                    ->helperText('При переходе проверяется готовность заказа. Пропустить неподтверждённый этап нельзя.')
                    ->live()->required(),
                Select::make('manager_id')->label('Ответственный')
                    ->options(fn (): array => AssignOrderManagerAction::options())->searchable()->required(),
                Textarea::make('next_action')->label('Следующий шаг')->placeholder('Позвонить клиенту и согласовать доставку')
                    ->rows(2)->maxLength(1000)->required(fn (Get $get): bool => $get('stage') !== 'completed'),
                DateTimePicker::make('control_at')->label('Срок контроля')->timezone('Europe/Minsk')
                    ->helperText('Минское время')->seconds(false)->native(false)->required(fn (Get $get): bool => $get('stage') !== 'completed'),
                Toggle::make('on_hold')->label('Отложить заказ до срока контроля'),
                Textarea::make('comment')->label('Комментарий / причина возврата на этап')->rows(2)->maxLength(3000),
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderDealControl::class)->save($record, auth()->user(), $data);
                $record->refresh();
                Notification::make()->success()->title('Этап и контроль сделки сохранены')->send();
            });
    }
}
