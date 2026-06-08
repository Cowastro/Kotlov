<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('import_prices')
                ->label('Импорт цен')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('CSV/XLSX-файл с ценами')
                        ->helperText('Артикул: sku или articul. Обновляются только price, price_old, in_stock, stock_qty, supplier_id/currency. Фото, описание, SEO и slug не трогаются.')
                        ->required()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
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
                    $storedFile = is_array($data['csv_file'])
                        ? reset($data['csv_file'])
                        : $data['csv_file'];

                    $path      = Storage::disk('local')->path($storedFile);
                    $delimiter = $data['delimiter'];
                    $isDryRun  = $data['dry_run'] ?? true;

                    if (!file_exists($path)) {
                        Notification::make()->danger()->title('Файл не найден')->send();
                        return;
                    }

                    $rows = $this->readPriceRows($path, $delimiter);
                    $header = array_shift($rows);
                    if (!$header || empty($rows)) {
                        Notification::make()->danger()->title('Файл пустой или неверный формат')->send();
                        return;
                    }

                    // Нормализуем заголовки (убираем BOM, пробелы, приводим к нижнему регистру)
                    $header = array_map(
                        fn($h) => $this->normalizeHeader((string) $h),
                        $header
                    );

                    if (!in_array('sku', $header, true)) {
                        Notification::make()
                            ->danger()
                            ->title('Колонка артикула обязательна')
                            ->body('Нужна колонка sku/articul/артикул. Найденные колонки: ' . implode(', ', $header))
                            ->send();
                        return;
                    }

                    $updated   = 0;
                    $notFound  = 0;
                    $skipped   = 0;
                    $found     = 0;
                    $notFoundSkus = [];

                    foreach ($rows as $row) {
                        $rowData = array_combine(
                            $header,
                            array_slice(array_pad($row, count($header), null), 0, count($header))
                        );
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

                        $found++;
                        $fields = [];

                        if (isset($rowData['price']) && $rowData['price'] !== '') {
                            $fields['price'] = $this->parseDecimal($rowData['price']);
                        }
                        if (isset($rowData['price_old']) && $rowData['price_old'] !== '') {
                            $fields['price_old'] = $this->parseDecimal($rowData['price_old']) ?: null;
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
                        if (isset($rowData['currency']) && $rowData['currency'] !== '') {
                            $fields['currency'] = trim((string) $rowData['currency']);
                        }
                        if (isset($rowData['supplier_id']) && $rowData['supplier_id'] !== '') {
                            $supplierId = (int) $rowData['supplier_id'];
                            if ($supplierId && User::whereKey($supplierId)->exists()) {
                                $fields['supplier_id'] = $supplierId;
                            }
                        }

                        if (!empty($fields)) {
                            if (!$isDryRun) {
                                $product->update($fields);
                            }
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    }

                    // Удаляем временный файл
                    if (!$isDryRun) {
                        Storage::disk('local')->delete($storedFile);
                    }

                    $body = "Найдено: {$found} | Обновлено: {$updated} | Не найдено: {$notFound} | Пропущено: {$skipped}";
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

    private function readPriceRows(string $path, string $delimiter): array
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim(ltrim($header, "\xEF\xBB\xBF")));
        $header = str_replace([' ', '-'], '_', $header);

        return match ($header) {
            'артикул', 'article', 'artikul', 'articul', 'vendor_code', 'code' => 'sku',
            'old_price', 'старая_цена', 'price_old_byn' => 'price_old',
            'наличие', 'available', 'stock', 'availability' => 'in_stock',
            'количество', 'qty', 'quantity', 'остаток', 'stock_quantity' => 'stock_qty',
            'поставщик', 'supplier', 'supplier_name' => 'supplier_id',
            'валюта', 'currency_code' => 'currency',
            default => $header,
        };
    }

    private function parseDecimal(mixed $value): float
    {
        $value = str_replace(["\xc2\xa0", ' '], '', (string) $value);
        $value = str_replace(',', '.', $value);

        return (float) preg_replace('/[^0-9.\-]/', '', $value);
    }
}
