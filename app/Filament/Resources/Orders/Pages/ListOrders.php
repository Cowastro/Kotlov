<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
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
        $staleLeadCount = Order::query()->staleUnprocessed()->count();

        $tabs = [
            'all' => Tab::make('Все')
                ->badge(Order::count()),

            'attention' => Tab::make('Нужна реакция')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->withOperationalProblem('needs_attention'))
                ->badge($attentionCount ?: null)
                ->badgeColor('danger'),

            'stale_leads' => Tab::make('Старые без реакции')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->staleUnprocessed())
                ->badge($staleLeadCount ?: null)
                ->badgeColor('warning'),

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
