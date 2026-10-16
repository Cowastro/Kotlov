<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Widgets\CommercialDocumentsOverview;
use App\Filament\Widgets\CommercialManagerPerformance;
use App\Models\CommercialDocument;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListCommercialDocuments extends ListRecords
{
    protected static string $resource = CommercialDocumentResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Создать КП или счёт')];
    }

    protected function getHeaderWidgets(): array
    {
        return [CommercialDocumentsOverview::class, CommercialManagerPerformance::class];
    }

    public function getSubheading(): ?string
    {
        return 'КП, счета, контроль сроков, решения клиентов и переход согласованной продажи в заказ.';
    }

    public function getTabs(): array
    {
        $needsAction = fn (Builder $query): Builder => $query
            ->currentRevision()
            ->where('type', 'quote')
            ->where(function (Builder $control): void {
                $control->where('status', 'needs_revision')
                    ->orWhere(function (Builder $sent): void {
                        $sent->where('status', 'sent')->whereDate('valid_until', '<=', today()->addDays(3));
                    })->orWhere(function (Builder $accepted): void {
                        $accepted->where('status', 'accepted')->whereDoesntHave('order');
                    });
            });

        return [
            'control' => Tab::make('Требует действия')->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing($needsAction)
                ->badge($needsAction(CommercialDocument::query())->count() ?: null)->badgeColor('warning'),
            'active' => Tab::make('В работе')->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->currentRevision()->whereIn('status', ['draft', 'sent', 'needs_revision']))
                ->badge(CommercialDocument::query()->currentRevision()->whereIn('status', ['draft', 'sent', 'needs_revision'])->count() ?: null),
            'won' => Tab::make('Выиграно')->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['accepted', 'paid']))
                ->badge(CommercialDocument::query()->whereIn('status', ['accepted', 'paid'])->count() ?: null)->badgeColor('success'),
            'lost' => Tab::make('Потеряно')->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['rejected', 'expired', 'cancelled']))
                ->badge(CommercialDocument::query()->whereIn('status', ['rejected', 'expired', 'cancelled'])->count() ?: null)->badgeColor('danger'),
            'all' => Tab::make('Все')->badge(CommercialDocument::query()->count()),
        ];
    }
}
