<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CommercialManagerPerformance extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $periodStart = today()->subDays(29);
        $decided = fn (Builder $query): Builder => $query
            ->where('type', 'quote')
            ->where(function (Builder $outcome) use ($periodStart): void {
                $outcome->where(function (Builder $won) use ($periodStart): void {
                    $won->where('status', 'accepted')->whereDate('accepted_at', '>=', $periodStart);
                })->orWhere(function (Builder $lost) use ($periodStart): void {
                    $lost->whereIn('status', ['rejected', 'expired'])->whereDate('closed_at', '>=', $periodStart);
                });
            });

        return $table
            ->heading('Эффективность менеджеров')
            ->description('Результаты по коммерческим предложениям за последние 30 дней.')
            ->query(fn (): Builder => User::query()
                ->whereHas('managedCommercialDocuments', $decided)
                ->withCount([
                    'managedCommercialDocuments as commercial_decided_count' => $decided,
                    'managedCommercialDocuments as commercial_won_count' => fn (Builder $query): Builder => $query
                        ->where('type', 'quote')->where('status', 'accepted')->whereDate('accepted_at', '>=', $periodStart),
                    'managedCommercialDocuments as commercial_lost_count' => fn (Builder $query): Builder => $query
                        ->where('type', 'quote')->whereIn('status', ['rejected', 'expired'])->whereDate('closed_at', '>=', $periodStart),
                ])
                ->withSum([
                    'managedCommercialDocuments as commercial_won_total' => fn (Builder $query): Builder => $query
                        ->where('type', 'quote')->where('status', 'accepted')->whereDate('accepted_at', '>=', $periodStart),
                ], 'total')
                ->orderByDesc('commercial_won_count'))
            ->columns([
                TextColumn::make('name')->label('Менеджер')->weight('bold'),
                TextColumn::make('commercial_decided_count')->label('Решений')->numeric()->alignCenter(),
                TextColumn::make('commercial_won_count')->label('Согласовано')->numeric()->alignCenter()->color('success'),
                TextColumn::make('commercial_lost_count')->label('Потеряно')->numeric()->alignCenter()->color('danger'),
                TextColumn::make('commercial_conversion')
                    ->label('Конверсия')
                    ->state(fn (User $record): string => number_format(
                        $record->commercial_decided_count > 0
                            ? $record->commercial_won_count / $record->commercial_decided_count * 100
                            : 0,
                        1,
                        ',',
                        ' ',
                    ).'%')
                    ->badge()
                    ->color(fn (User $record): string => match (true) {
                        $record->commercial_decided_count === 0 => 'gray',
                        $record->commercial_won_count / $record->commercial_decided_count >= 0.5 => 'success',
                        $record->commercial_won_count / $record->commercial_decided_count >= 0.25 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('commercial_won_total')->label('Сумма согласованных')->money('BYN')->alignRight(),
            ])
            ->paginated(false)
            ->emptyStateHeading('За последние 30 дней решений по КП ещё нет');
    }
}
