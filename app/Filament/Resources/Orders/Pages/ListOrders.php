<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\QuickOrderAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    private const WORK_TABS = ['work_queue', 'attention', 'my', 'unassigned', 'all'];

    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.orders.queue-navigation')
                ->viewData(fn (): array => ['tabs' => $this->getCachedTabs(), 'workTabs' => self::WORK_TABS]),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    public function selectOrderTab(string $tab): void
    {
        if (! isset($this->getCachedTabs()[$tab])) {
            return;
        }

        $this->activeTab = $tab;
        $this->updatedActiveTab();
    }

    protected function getHeaderActions(): array
    {
        return [
            QuickOrderAction::make(),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $colors = [
            'new' => 'info',
            'checking' => 'warning',
            'confirmed' => 'success',
            'processing' => 'warning',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
        ];

        $workQueueCount = $this->ordersQuery()->operationallyActive()->count();
        $unassigned = $this->ordersQuery()->operationallyActive()->withoutResponsible()->count();
        $myCount = $this->ordersQuery()->where('manager_id', auth()->id())->count();
        $attentionCount = $this->ordersQuery()->withOperationalProblem('needs_attention')->count();
        $missingPriceCount = $this->ordersQuery()->operationallyActive()->withOperationalProblem('missing_price')->count();
        $missingSupplierCount = $this->ordersQuery()->operationallyActive()->withOperationalProblem('missing_supplier')->count();
        $marginRiskCount = $this->ordersQuery()
            ->operationallyActive()
            ->where(fn (Builder $query): Builder => $query
                ->withOperationalProblem('negative_margin')
                ->orWhere(fn (Builder $part): Builder => $part->withOperationalProblem('low_margin')))
            ->count();
        $archivedCount = $this->ordersQuery()->archived()->count();

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
                ->modifyQueryUsing(fn (Builder $query) => $query->operationallyActive()->withoutResponsible())
                ->badge($unassigned ?: null)
                ->badgeColor('danger'),

            'all' => Tab::make('Все заказы')
                ->badge($this->ordersQuery()->count()),
        ];

        $statusCounts = $this->ordersQuery()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        foreach (Order::STATUSES as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $key))
                ->badge($statusCounts[$key] ?? null)
                ->badgeColor($colors[$key] ?? 'gray');
        }

        return $tabs;
    }

    protected function ordersQuery(): Builder
    {
        return static::getResource()::getEloquentQuery();
    }
}
