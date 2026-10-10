<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archiveHistorical')
                ->label(fn (): string => 'В архив все старые ('.Order::query()->historicalUnprocessed()->count().')')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => Order::query()->historicalUnprocessed()->exists())
                ->form([
                    Textarea::make('reason')
                        ->label('Общая причина закрытия')
                        ->helperText('Причина и автор сохранятся в истории каждого заказа. Сами заказы не удаляются.')
                        ->default('Архивная заявка до запуска нового рабочего процесса')
                        ->rows(3)
                        ->minLength(3)
                        ->maxLength(1000)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Архивировать все старые заявки')
                ->modalDescription(fn (): string => 'В архив попадут '.Order::query()->historicalUnprocessed()->count().' новых неоплаченных заявок старше границы рабочего периода. Новые, оплаченные и уже обработанные заказы не изменятся.')
                ->modalSubmitActionLabel('В архив как неактуальные')
                ->action(function (array $data): void {
                    $changed = 0;

                    Order::query()
                        ->historicalUnprocessed()
                        ->orderBy('id')
                        ->chunkById(100, function ($orders) use ($data, &$changed): void {
                            foreach ($orders as $order) {
                                if ($order->markIrrelevant($data['reason'])) {
                                    $changed++;
                                }
                            }
                        });

                    Notification::make()
                        ->title('Старые заявки перемещены в архив')
                        ->body("В архиве: {$changed}. Заказы, причина и история сохранены.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $colors = [
            'new' => 'info',
            'confirmed' => 'warning',
            'processing' => 'warning',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
        ];

        $workQueueCount = Order::query()->operationallyActive()->count();
        $unassigned = Order::query()
            ->operationallyActive()
            ->whereNull('manager_id')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('assigned_to')
                ->orWhere('assigned_to', ''))
            ->count();
        $myCount = Order::where('manager_id', auth()->id())->count();
        $attentionCount = Order::query()->withOperationalProblem('needs_attention')->count();
        $missingPriceCount = Order::query()->operationallyActive()->withOperationalProblem('missing_price')->count();
        $missingSupplierCount = Order::query()->operationallyActive()->withOperationalProblem('missing_supplier')->count();
        $marginRiskCount = Order::query()
            ->operationallyActive()
            ->where(fn (Builder $query): Builder => $query
                ->withOperationalProblem('negative_margin')
                ->orWhere(fn (Builder $part): Builder => $part->withOperationalProblem('low_margin')))
            ->count();
        $historicalLeadCount = Order::query()->historicalUnprocessed()->count();
        $archivedCount = Order::query()->archived()->count();

        $tabs = [
            'work_queue' => Tab::make('В работе')
                ->icon('heroicon-o-briefcase')
                ->modifyQueryUsing(fn (Builder $query) => $query->operationallyActive())
                ->badge($workQueueCount ?: null)
                ->badgeColor('info'),

            'attention' => Tab::make('Нужна реакция')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->withOperationalProblem('needs_attention'))
                ->badge($attentionCount ?: null)
                ->badgeColor('danger'),

            'historical_leads' => Tab::make('Исторические заявки')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->historicalUnprocessed())
                ->badge($historicalLeadCount ?: null)
                ->badgeColor('gray'),

            'archived' => Tab::make('Архив / неактуальные')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->archived())
                ->badge($archivedCount ?: null)
                ->badgeColor('gray'),

            'missing_price' => Tab::make('Без входной цены')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->operationallyActive()->withOperationalProblem('missing_price'))
                ->badge($missingPriceCount ?: null)
                ->badgeColor('danger'),

            'missing_supplier' => Tab::make('Без поставщика')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->operationallyActive()->withOperationalProblem('missing_supplier'))
                ->badge($missingSupplierCount ?: null)
                ->badgeColor('danger'),

            'margin_risk' => Tab::make('Маржа под риском')
                ->icon('heroicon-o-chart-bar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->operationallyActive()
                    ->where(fn (Builder $part): Builder => $part
                        ->withOperationalProblem('negative_margin')
                        ->orWhere(fn (Builder $lowMargin): Builder => $lowMargin->withOperationalProblem('low_margin'))))
                ->badge($marginRiskCount ?: null)
                ->badgeColor('warning'),

            'my' => Tab::make('Мои заказы')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('manager_id', auth()->id()))
                ->badge($myCount ?: null)
                ->badgeColor('info'),

            'unassigned' => Tab::make('Без ответственного')
                ->icon('heroicon-o-user-minus')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->operationallyActive()
                    ->whereNull('manager_id')
                    ->where(fn (Builder $owner): Builder => $owner
                        ->whereNull('assigned_to')
                        ->orWhere('assigned_to', '')))
                ->badge($unassigned ?: null)
                ->badgeColor('danger'),

            'all' => Tab::make('Все заказы')
                ->badge(Order::count()),
        ];

        foreach (Order::STATUSES as $key => $label) {
            $count = Order::where('status', $key)->count();
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $key))
                ->badge($count ?: null)
                ->badgeColor($colors[$key] ?? 'gray');
        }

        return $tabs;
    }
}
