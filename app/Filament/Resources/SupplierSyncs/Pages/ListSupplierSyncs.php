<?php

namespace App\Filament\Resources\SupplierSyncs\Pages;

use App\Filament\Resources\SupplierSyncs\SupplierSyncResource;
use App\Models\SupplierSync;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSupplierSyncs extends ListRecords
{
    protected static string $resource = SupplierSyncResource::class;

    public function getTitle(): string
    {
        return 'Обновление товаров';
    }

    public function getSubheading(): ?string
    {
        return 'Управление обновлением товаров от поставщиков';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dry_run_all')
                ->label('Пробный запуск всех')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(fn () => $this->runAll('dry_run')),

            Action::make('update_all')
                ->label('Обновить все активные')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->runAll('update')),

            Action::make('clear_logs')
                ->label('Очистить логи')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    SupplierSync::cleanLogs();
                    Notification::make()->success()->title('Логи очищены')->send();
                }),

            CreateAction::make()
                ->label('Добавить поставщика'),
        ];
    }

    private function runAll(string $mode): void
    {
        $ok = 0;
        $failed = 0;

        SupplierSync::query()
            ->where('is_active', true)
            ->get()
            ->each(function (SupplierSync $sync) use ($mode, &$ok, &$failed): void {
                $result = $sync->runSync($mode);
                $result['exit_code'] === 0 ? $ok++ : $failed++;
            });

        $notification = Notification::make()
            ->title('Групповой запуск завершен')
            ->body("Успешно: {$ok}. Ошибок: {$failed}.")
            ->persistent();

        $failed === 0 ? $notification->success() : $notification->warning();
        $notification->send();
    }
}
