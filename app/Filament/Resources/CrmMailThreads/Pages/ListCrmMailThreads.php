<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailThread;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCrmMailThreads extends ListRecords
{
    protected static string $resource = CrmMailThreadResource::class;

    public function getSubheading(): ?string
    {
        return 'Каждое новое письмо становится контролируемым лидом и связывается с контактами CRM по email.';
    }

    public function getTabs(): array
    {
        return [
            'inbox' => Tab::make('Рабочая почта')
                ->icon('heroicon-o-inbox')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('status', ['draft', 'spam']))
                ->badge(CrmMailThread::query()->whereNotIn('status', ['draft', 'spam'])->count()),
            'drafts' => Tab::make('Черновики')
                ->icon('heroicon-o-document-text')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft'))
                ->badge(CrmMailThread::query()->where('status', 'draft')->count() ?: null)
                ->badgeColor('warning'),
            'spam' => Tab::make('Спам')
                ->icon('heroicon-o-no-symbol')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'spam'))
                ->badge(CrmMailThread::query()->where('status', 'spam')->count() ?: null)
                ->badgeColor('danger'),
            'trash' => Tab::make('Корзина')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed())
                ->badge(CrmMailThread::onlyTrashed()->count() ?: null)
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('empty_trash')
                ->label('Очистить корзину')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->activeTab === 'trash'
                    && auth()->user()?->isAdmin() === true
                    && CrmMailThread::onlyTrashed()->exists())
                ->requiresConfirmation()
                ->modalHeading('Очистить корзину полностью?')
                ->modalDescription('Все переписки в корзине и их письма будут удалены навсегда. Это действие нельзя отменить.')
                ->action(function (): void {
                    $count = CrmMailThread::onlyTrashed()->count();
                    CrmMailThread::onlyTrashed()->forceDelete();

                    Notification::make()
                        ->success()
                        ->title('Корзина очищена')
                        ->body('Удалено переписок: '.$count)
                        ->send();
                }),
        ];
    }
}
