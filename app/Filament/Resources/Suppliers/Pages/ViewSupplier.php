<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Console\Commands\SyncChistyBeregCommand;
use App\Console\Commands\SyncCooperDoorsCommand;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Services\Suppliers\ChistyBeregPriceList;
use App\Services\Suppliers\CooperWholesalePriceList;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chisty_bereg_stock')
                ->label('Загрузить остатки и цены')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncChistyBeregCommand::SUPPLIER_CODE)
                ->modalDescription('Утренний файл «Остатки и цены Чистого берега» (xls). Остатки, опт и РРЦ обновятся в течение 10 минут.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xls')
                        ->required()
                        // An old .xls is seen by the server as application/CDFV2 or x-ole-storage: the sheet check below decides.
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(ChistyBeregPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет листа «Список» с товарами')->send();

                        return;
                    }
                    ChistyBeregPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Notification::make()->success()
                        ->title('Файл принят: '.$rows.' позиций')
                        ->body('Остатки, опт и РРЦ обновятся в течение 10 минут.')
                        ->send();
                }),
            Action::make('cooper_wholesale')
                ->label('Загрузить оптовый прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->code === SyncCooperDoorsCommand::SUPPLIER_CODE)
                ->modalDescription('Прайс дверей Cooper в xlsx (колонки «Наименование», «Цена с ндс», «РРЦ»). Закупка запишется дверям Cooper, розница остаётся как на cooper.by.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->disk('local')
                        ->directory('supplier-price-uploads')
                        ->storeFileNamesIn('file_name'),
                ])
                ->action(function (array $data): void {
                    $stored = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $path = Storage::disk('local')->path((string) $stored);
                    $rows = is_file($path) ? CooperWholesalePriceList::parse($path) : [];
                    Storage::disk('local')->delete((string) $stored);
                    if ($rows === []) {
                        Notification::make()->danger()->title('В файле нет дверей COOPER с ценой')->send();

                        return;
                    }
                    CooperWholesalePriceList::save($rows, (string) ($data['file_name'] ?? 'прайс'));
                    Artisan::call('supplier:sync-cooper-doors', ['--apply' => true]);
                    preg_match_all('/^metric\.cooper_doors\.(with_cost|no_cost|rrc_differs)=(\d+)$/m', Artisan::output(), $m);
                    $metrics = array_combine($m[1], $m[2]);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.count($rows).' строк Cooper')
                        ->body(sprintf('Закупка записана дверям: %d. Не нашлось в прайсе: %d. РРЦ прайса отличается от цены сайта: %d.',
                            (int) ($metrics['with_cost'] ?? 0), (int) ($metrics['no_cost'] ?? 0), (int) ($metrics['rrc_differs'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
