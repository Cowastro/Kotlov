<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Console\Commands\SyncAkvateploservisCommand;
use App\Console\Commands\SyncChistyBeregCommand;
use App\Console\Commands\SyncCooperDoorsCommand;
use App\Console\Commands\SyncKaminproffCommand;
use App\Console\Commands\SyncTeplotopCommand;
use App\Console\Commands\SyncPrestizhTeploCommand;
use App\Console\Commands\SyncVistarCommand;
use App\Console\Commands\SyncPrometallCommand;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Services\Suppliers\AkvateploservisPriceList;
use App\Services\Suppliers\ChistyBeregPriceList;
use App\Services\Suppliers\CooperWholesalePriceList;
use App\Services\Suppliers\KaminproffPriceList;
use App\Services\Suppliers\TeplotopPriceList;
use App\Services\Suppliers\TeplovSukhovPriceList;
use App\Services\Suppliers\PrestizhTeploPriceList;
use App\Services\Suppliers\VistarPriceList;
use App\Services\Suppliers\PrometallPriceList;
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
            Action::make('prometall_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncPrometallCommand::SUPPLIER_CODE)
                ->modalDescription('«ПРАЙС-ЛИСТ PROMETALL Опт» (xlsx). Опт и РРЦ обновятся сразу, строки привяжутся к карточкам ProMetall.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(PrometallPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса ProMetall (артикул p…, РРЦ)')->send();

                        return;
                    }
                    PrometallPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Artisan::call('supplier:sync-prometall', ['--apply' => true]);
                    $syncOutput = Artisan::output();
                    // The same prices on sbg.by: the ProMetall goods of the shop take the new RRC right away.
                    Artisan::call('deal:sync-brand-prices', ['--supplier' => 'prometall', '--apply' => true]);
                    preg_match_all('/^metric\.prometall\.([a-z_]+)=(\d+)$/m', $syncOutput, $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['ean'] ?? 0) + (int) ($metrics['article'] ?? 0) + (int) ($metrics['exact_name'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d. Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('teplotop_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncTeplotopCommand::SUPPLIER_CODE)
                ->modalDescription('Два файла ТеплоТоп-Бел: «ОПТ_Прайс» и «РРЦ_Прайс» (xlsx). Опт и РРЦ обновятся сразу, строки привяжутся к карточкам LEMAX, KRATS, EKF и арматуры.')
                ->schema([
                    FileUpload::make('opt')
                        ->label('ОПТ прайс (xlsx)')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                    FileUpload::make('rrc')
                        ->label('РРЦ прайс (xlsx)')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = fn (string $field): string => (string) (is_array($data[$field]) ? reset($data[$field]) : $data[$field]);
                    [$optPath, $rrcPath] = [Storage::disk('local')->path($stored('opt')), Storage::disk('local')->path($stored('rrc'))];
                    $rows = is_file($optPath) ? count(TeplotopPriceList::parse($optPath, $rrcPath)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete([$stored('opt'), $stored('rrc')]);
                        Notification::make()->danger()->title('В файлах нет строк прайса ТеплоТоп (модель, опт, РРЦ)')->send();

                        return;
                    }
                    TeplotopPriceList::store($optPath, $rrcPath);
                    Storage::disk('local')->delete([$stored('opt'), $stored('rrc')]);
                    Artisan::call('supplier:sync-teplotop', ['--apply' => true]);
                    preg_match_all('/^metric\.teplotop\.([a-z_]+)=(\d+)$/m', Artisan::output(), $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['article'] ?? 0) + (int) ($metrics['model'] ?? 0) + (int) ($metrics['manual'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d (из них радиаторов %d). Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['radiators'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            // Our own Теплов и Сухов price list (on the ТиС and the СанБизнесГруп page): the upload only shows what would change, the prices change on «Применить».
            Action::make('teplov_sukhov_price')
                ->label('Загрузить прайс ТиС')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => in_array($this->getRecord()->code, ['teplov', 'sanbusinessgroup'], true))
                ->modalDescription('Наш прайс «ТиС_ОПТ_<дата>_SBG» (xlsx, все листы). Цены пока не меняются: покажем, сколько изменится, применить — кнопкой «Применить прайс ТиС».')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(TeplovSukhovPriceList::parse($path)['rows']) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса (артикул, розница, опт)')->send();

                        return;
                    }
                    TeplovSukhovPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    $metrics = $this->teplovSukhovSync(false);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Сопоставлено с карточками: %d. Цен изменится: %d (вверх %d, вниз %d). Не найдено: %d, неоднозначно: %d, конфликтов: %d. Чтобы изменить цены, нажмите «Применить прайс ТиС».',
                            $metrics['matched'] ?? 0, $metrics['changed'] ?? 0, $metrics['up'] ?? 0, $metrics['down'] ?? 0,
                            $metrics['unmatched'] ?? 0, $metrics['ambiguous'] ?? 0, ($metrics['conflict'] ?? 0) + ($metrics['price_list_conflict'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('teplov_sukhov_apply')
                ->label('Применить прайс ТиС')
                ->icon('heroicon-o-check')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->getRecord()->code, ['teplov', 'sanbusinessgroup'], true) && TeplovSukhovPriceList::storedPath() !== '')
                ->requiresConfirmation()
                ->modalDescription('Розничные цены карточек Теплов и Сухов станут ценами загруженного прайса (и на sbg.by), опт сохранится у предложений поставщика.')
                ->action(function (): void {
                    $metrics = $this->teplovSukhovSync(true);
                    // The same prices on sbg.by (deal.by): the shop items found by the ТиС article.
                    Artisan::call('deal:sync-brand-prices', ['--supplier' => 'teplov', '--apply' => true]);
                    preg_match('/^metric\.deal_brand_prices\.updated=(\d+)$/m', Artisan::output(), $deal);
                    Notification::make()->success()
                        ->title('Прайс применён')
                        ->body(sprintf('Цен обновлено: %d (вверх %d, вниз %d). Без изменения: %d. Пропущено: %d. На sbg.by (deal.by) обновлено цен: %d.',
                            $metrics['changed'] ?? 0, $metrics['up'] ?? 0, $metrics['down'] ?? 0, $metrics['unchanged'] ?? 0,
                            ($metrics['unmatched'] ?? 0) + ($metrics['ambiguous'] ?? 0) + ($metrics['conflict'] ?? 0) + ($metrics['price_list_conflict'] ?? 0),
                            (int) ($deal[1] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('vistar_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncVistarCommand::SUPPLIER_CODE)
                ->modalDescription('Прайс VISTAR (xlsx, все листы: BAXI, Viessmann, Viena, Reflex). Опт, розница и наличие обновятся сразу, строки привяжутся к карточкам по артикулу и модели.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(VistarPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса VISTAR (артикул, наименование, опт)')->send();

                        return;
                    }
                    VistarPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Artisan::call('supplier:sync-vistar', ['--apply' => true]);
                    preg_match_all('/^metric\.vistar\.([a-z_]+)=(\d+)$/m', Artisan::output(), $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['article'] ?? 0) + (int) ($metrics['model'] ?? 0) + (int) ($metrics['manual'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d. Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('prestizh_teplo_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncPrestizhTeploCommand::SUPPLIER_CODE)
                ->modalDescription('Прайс Престиж Тепло (xlsm из рассылки, все листы брендов). Цены в евро пересчитаются по курсу из прайса, строки привяжутся к карточкам по артикулу и модели.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsm / xlsx')
                        ->required()
                        ->rules(['extensions:xlsm,xlsx,xls'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(PrestizhTeploPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса Престиж Тепло (артикул, наименование, ОЦ/РЦ)')->send();

                        return;
                    }
                    PrestizhTeploPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Artisan::call('supplier:sync-prestizh-teplo', ['--apply' => true]);
                    preg_match_all('/^metric\.prestizh\.([a-z_]+)=(\d+)$/m', Artisan::output(), $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['article'] ?? 0) + (int) ($metrics['model'] ?? 0) + (int) ($metrics['manual'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d. Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('akvateploservis_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncAkvateploservisCommand::SUPPLIER_CODE)
                ->modalDescription('«GALMET прайс-лист» Акватеплосервис (xls). Опт, РРЦ и остатки склада обновятся сразу, строки привяжутся к карточкам Galmet и Apamet.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xls / xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(AkvateploservisPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса Galmet (артикул, наименование, опт, РРЦ)')->send();

                        return;
                    }
                    AkvateploservisPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Artisan::call('supplier:sync-akvateploservis', ['--apply' => true]);
                    $syncOutput = Artisan::output();
                    // The same prices on sbg.by: the Galmet goods of the shop take the new РРЦ right away.
                    Artisan::call('deal:sync-brand-prices', ['--supplier' => 'akvateploservis', '--apply' => true]);
                    preg_match_all('/^metric\.akvateploservis\.([a-z_]+)=(\d+)$/m', $syncOutput, $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['article'] ?? 0) + (int) ($metrics['model'] ?? 0) + (int) ($metrics['manual'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d. На складе: %d. Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['in_stock'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
                        ->send();
                }),
            Action::make('kaminproff_price')
                ->label('Загрузить прайс')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->code === SyncKaminproffCommand::SUPPLIER_CODE)
                ->modalDescription('«Прайс Fireway» Камин-профессионал (xlsx, все листы). Опт и РРЦ обновятся сразу, строки привяжутся к карточкам Fireway.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл xlsx')
                        ->required()
                        ->rules(['extensions:xls,xlsx'])
                        ->maxSize(30720)
                        ->disk('local')
                        ->directory('supplier-price-uploads'),
                ])
                ->action(function (array $data): void {
                    $stored = (string) (is_array($data['file']) ? reset($data['file']) : $data['file']);
                    $path = Storage::disk('local')->path($stored);
                    $rows = is_file($path) ? count(KaminproffPriceList::parse($path)) : 0;
                    if ($rows === 0) {
                        Storage::disk('local')->delete($stored);
                        Notification::make()->danger()->title('В файле нет строк прайса Fireway (название, опт, ррц)')->send();

                        return;
                    }
                    KaminproffPriceList::store($path);
                    Storage::disk('local')->delete($stored);
                    Artisan::call('supplier:sync-kaminproff', ['--apply' => true]);
                    $syncOutput = Artisan::output();
                    // The same prices on sbg.by: the Fireway goods of the shop take the new РРЦ right away.
                    Artisan::call('deal:sync-brand-prices', ['--supplier' => 'kaminproff', '--apply' => true]);
                    preg_match_all('/^metric\.kaminproff\.([a-z_]+)=(\d+)$/m', $syncOutput, $m);
                    $metrics = array_combine($m[1], $m[2]);
                    $linked = (int) ($metrics['linked_before'] ?? 0) + (int) ($metrics['model'] ?? 0) + (int) ($metrics['manual'] ?? 0);
                    Notification::make()->success()
                        ->title('Прайс загружен: '.$rows.' позиций')
                        ->body(sprintf('Привязано к карточкам: %d. Предложено на подтверждение: %d. Без карточки: %d. Цен на сайте обновлено: %d.',
                            $linked, (int) ($metrics['suggested'] ?? 0), (int) ($metrics['unlinked'] ?? 0), (int) ($metrics['retail_updated'] ?? 0)))
                        ->persistent()
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

    /** @return array<string,int> the metric.teplov_sukhov.* lines of the sync */
    private function teplovSukhovSync(bool $apply): array
    {
        Artisan::call('supplier:sync-teplov-sukhov-retail', $apply ? ['--apply' => true] : []);
        preg_match_all('/^metric\.teplov_sukhov\.([a-z_]+)=(\d+)$/m', Artisan::output(), $m);

        return array_map('intval', array_combine($m[1], $m[2]));
    }
}
