<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Models\CrmCounterparty;
use App\Services\Crm\Imports\OneCCrmImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListCrmCounterparties extends ListRecords
{
    protected static string $resource = CrmCounterpartyResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все'),
            'leads' => Tab::make('Новые лиды')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'lead'))
                ->badge(fn (): int => CrmCounterparty::query()->where('status', 'lead')->count()),
            'email' => Tab::make('Из почты')
                ->modifyQueryUsing(fn ($query) => $query->where('source', 'email'))
                ->badge(fn (): int => CrmCounterparty::query()->where('source', 'email')->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_one_c')
                ->label('Импорт из 1С')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                ->form([
                    Select::make('type')
                        ->label('Что импортируем')
                        ->options(OneCCrmImporter::TYPES)
                        ->required()
                        ->default('trade_contacts'),
                    FileUpload::make('file')
                        ->label('Файл MXL из 1С')
                        ->helperText('Сначала выполните пробный запуск. Для УТ используйте отчёт «Контактная информация по партнёрам».')
                        ->disk('local')
                        ->directory('crm-imports')
                        ->maxSize(20480)
                        ->required(),
                    Toggle::make('dry_run')
                        ->label('Пробный запуск без сохранения')
                        ->default(true)
                        ->helperText('Покажет количество новых карточек, совпадений и конфликтов.'),
                ])
                ->action(function (array $data): void {
                    $storedFile = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $path = Storage::disk('local')->path($storedFile);
                    $dryRun = (bool) ($data['dry_run'] ?? true);

                    try {
                        $summary = app(OneCCrmImporter::class)->import(
                            $data['type'],
                            $path,
                            $dryRun,
                            auth()->id(),
                        );

                        Notification::make()
                            ->title($dryRun ? 'Проверка файла завершена' : 'Импорт CRM завершён')
                            ->body(implode("\n", [
                                "Строк: {$summary['rows']}",
                                "Новых карточек: {$summary['created']}",
                                "Совпадений: {$summary['matched']}",
                                "Телефонов и e-mail: {$summary['contact_points']}",
                                "Связано поставщиков: {$summary['suppliers_linked']}",
                                "Требуют разбора: {$summary['conflicts']}",
                                "Распознано старых звонков: {$summary['calls_matched']}",
                                $dryRun ? 'Данные не сохранены.' : 'Данные сохранены в CRM.',
                            ]))
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Импорт не выполнен')
                            ->body($exception->getMessage())->danger()->persistent()->send();
                    } finally {
                        Storage::disk('local')->delete($storedFile);
                    }
                }),
            CreateAction::make()->label('Создать контрагента'),
        ];
    }
}
