<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('import_prices')
                ->label('Импорт цен CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('CSV-файл с ценами')
                        ->helperText('Колонки: sku;price;price_old;in_stock;stock_qty — только указанные поля будут обновлены')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('price-imports'),

                    Select::make('delimiter')
                        ->label('Разделитель')
                        ->options([';' => 'Точка с запятой (;)', ',' => 'Запятая (,)', "\t" => 'Табуляция'])
                        ->default(';')
                        ->required(),

                    Toggle::make('dry_run')
                        ->label('Пробный запуск (без сохранения)')
                        ->default(true)
                        ->helperText('Включите, чтобы сначала проверить результат'),
                ])
                ->action(function (array $data): void {
                    $path      = Storage::disk('local')->path($data['csv_file']);
                    $delimiter = $data['delimiter'];
                    $isDryRun  = $data['dry_run'] ?? true;

                    if (!file_exists($path)) {
                        Notification::make()->danger()->title('Файл не найден')->send();
                        return;
                    }

                    $handle = fopen($path, 'r');
                    if (!$handle) {
                        Notification::make()->danger()->title('Не удалось открыть файл')->send();
                        return;
                    }

                    // Читаем заголовок
                    $header = fgetcsv($handle, 0, $delimiter);
                    if (!$header) {
                        Notification::make()->danger()->title('Файл пустой или неверный формат')->send();
                        fclose($handle);
                        return;
                    }

                    // Нормализуем заголовки (убираем BOM, пробелы, приводим к нижнему регистру)
                    $header = array_map(fn($h) => mb_strtolower(trim(ltrim($h, "\xEF\xBB\xBF"))), $header);

                    if (!in_array('sku', $header)) {
                        Notification::make()
                            ->danger()
                            ->title('Колонка "sku" обязательна')
                            ->body('Найденные колонки: ' . implode(', ', $header))
                            ->send();
                        fclose($handle);
                        return;
                    }

                    $updated   = 0;
                    $notFound  = 0;
                    $skipped   = 0;
                    $notFoundSkus = [];

                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rowData = array_combine($header, array_pad($row, count($header), null));
                        $sku = trim($rowData['sku'] ?? '');

                        if (!$sku) {
                            $skipped++;
                            continue;
                        }

                        $product = Product::where('sku', $sku)->first();

                        if (!$product) {
                            $notFound++;
                            if (count($notFoundSkus) < 10) {
                                $notFoundSkus[] = $sku;
                            }
                            continue;
                        }

                        $fields = [];

                        if (isset($rowData['price']) && $rowData['price'] !== '') {
                            $fields['price'] = (float) str_replace(',', '.', $rowData['price']);
                        }
                        if (isset($rowData['price_old']) && $rowData['price_old'] !== '') {
                            $fields['price_old'] = (float) str_replace(',', '.', $rowData['price_old']) ?: null;
                        }
                        if (isset($rowData['in_stock']) && $rowData['in_stock'] !== '') {
                            $fields['in_stock'] = in_array(
                                mb_strtolower(trim($rowData['in_stock'])),
                                ['1', 'true', 'да', 'yes', 'в наличии']
                            );
                        }
                        if (isset($rowData['stock_qty']) && $rowData['stock_qty'] !== '') {
                            $fields['stock_qty'] = (int) $rowData['stock_qty'];
                        }

                        if (!empty($fields)) {
                            if (!$isDryRun) {
                                $product->update($fields);
                            }
                            $updated++;
                        }
                    }

                    fclose($handle);

                    // Удаляем временный файл
                    if (!$isDryRun) {
                        Storage::disk('local')->delete($data['csv_file']);
                    }

                    $body = "Обновлено: {$updated} | Не найдено: {$notFound} | Пропущено: {$skipped}";
                    if ($notFoundSkus) {
                        $body .= "\nНеизвестные SKU: " . implode(', ', $notFoundSkus)
                            . ($notFound > 10 ? " и ещё " . ($notFound - 10) : '');
                    }
                    if ($isDryRun) {
                        $body .= "\n⚠️ Пробный запуск — данные НЕ сохранены";
                    }

                    Notification::make()
                        ->success()
                        ->title($isDryRun ? 'Пробный запуск завершён' : 'Импорт завершён')
                        ->body($body)
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
