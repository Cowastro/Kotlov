<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'Обновление товаров';
    protected static ?string $title = 'Обновление товаров';
    protected static ?string $slug = 'product-updates';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.product-updates';

    public ?string $selectedLogTitle = null;
    public ?string $selectedLogOutput = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
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
                ->action(fn () => $this->clearLogs()),
        ];
    }

    public function suppliers(): array
    {
        return collect(config('supplier_sync.suppliers', []))
            ->map(fn (array $supplier, string $key) => $this->supplierRow($key, $supplier))
            ->values()
            ->all();
    }

    public function runSupplier(string $key, string $mode): void
    {
        $supplier = config("supplier_sync.suppliers.{$key}");

        if (! $supplier) {
            Notification::make()->danger()->title('Поставщик не найден')->send();
            return;
        }

        $result = $this->runCommand($key, $supplier, $mode);

        Notification::make()
            ->title($result['exit_code'] === 0 ? 'Команда завершена' : 'Команда завершилась с ошибками')
            ->body(Str::limit($result['output'] ?: 'Вывод команды пуст.', 900))
            ->persistent()
            ->{$result['exit_code'] === 0 ? 'success' : 'danger'}()
            ->send();
    }

    public function runAll(string $mode): void
    {
        $active = collect(config('supplier_sync.suppliers', []))
            ->filter(fn (array $supplier) => $supplier['is_active'] ?? false);

        $ok = 0;
        $failed = 0;

        foreach ($active as $key => $supplier) {
            $result = $this->runCommand((string) $key, $supplier, $mode);
            $result['exit_code'] === 0 ? $ok++ : $failed++;
        }

        Notification::make()
            ->title('Групповой запуск завершен')
            ->body("Успешно: {$ok}. Ошибок: {$failed}.")
            ->persistent()
            ->{$failed === 0 ? 'success' : 'warning'}()
            ->send();
    }

    public function showLog(string $key): void
    {
        $row = $this->supplierRow($key, config("supplier_sync.suppliers.{$key}", []));
        $this->selectedLogTitle = $row['title'] . ' — последний лог';
        $this->selectedLogOutput = $row['last_log_path'] && file_exists($row['last_log_path'])
            ? file_get_contents($row['last_log_path'])
            : 'Логов пока нет.';
    }

    public function showSettings(string $key): void
    {
        $supplier = config("supplier_sync.suppliers.{$key}");

        Notification::make()
            ->title('Настройки поставщика')
            ->body($supplier
                ? "Ключ: {$key}\nКонфиг: config/supplier_sync.php\nКоманда: {$supplier['command']}"
                : 'Поставщик не найден.')
            ->persistent()
            ->info()
            ->send();
    }

    public function clearLogs(): void
    {
        $dir = storage_path('logs/supplier-sync');

        if (is_dir($dir)) {
            File::cleanDirectory($dir);
        }

        $this->selectedLogTitle = null;
        $this->selectedLogOutput = null;

        Notification::make()
            ->success()
            ->title('Логи очищены')
            ->send();
    }

    private function supplierRow(string $key, array $supplier): array
    {
        $status = $this->readStatus($key);
        $lastLogPath = $this->lastLogPath($key);

        return [
            'key' => $key,
            'name' => $supplier['name'] ?? $key,
            'code' => $supplier['code'] ?? $key,
            'title' => $supplier['title'] ?? ($supplier['name'] ?? $key),
            'description' => $supplier['description'] ?? '',
            'command' => $supplier['command'] ?? '',
            'source_url' => $supplier['source_url'] ?? null,
            'is_active' => (bool) ($supplier['is_active'] ?? false),
            'products_count' => $this->productsCount($supplier),
            'mappings_count' => $this->mappingsCount($supplier),
            'attributes_count' => $this->attributesCount($supplier),
            'photos_count' => $this->photosCount($supplier),
            'last_run' => $status['last_run'] ?? '—',
            'status' => $status['status'] ?? 'never',
            'last_log_path' => $lastLogPath,
        ];
    }

    private function runCommand(string $key, array $supplier, string $mode): array
    {
        $command = $supplier['command'] ?? null;

        if (! $command) {
            return ['exit_code' => 1, 'output' => 'Команда не настроена.'];
        }

        $parameters = $mode === 'update'
            ? ['--apply' => true]
            : ['--dry-run' => true];

        $exitCode = Artisan::call($command, $parameters);
        $output = trim(Artisan::output());

        $this->writeLog($key, $mode, $exitCode, $output);
        $this->writeStatus($key, [
            'last_run' => now()->format('d.m.Y H:i:s'),
            'status' => $exitCode === 0 ? ($mode === 'update' ? 'updated' : 'dry-run') : 'failed',
            'exit_code' => $exitCode,
        ]);

        return ['exit_code' => $exitCode, 'output' => $output];
    }

    private function productsCount(array $supplier): int
    {
        $code = $supplier['code'] ?? null;

        if (! $code) {
            return 0;
        }

        $productIds = DB::table('supplier_product_mappings')
            ->where('supplier_code', $code)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        return $productIds->count();
    }

    private function mappingsCount(array $supplier): int
    {
        return DB::table('supplier_product_mappings')
            ->where('supplier_code', $supplier['code'] ?? '')
            ->count();
    }

    private function attributesCount(array $supplier): int
    {
        $productIds = DB::table('supplier_product_mappings')
            ->where('supplier_code', $supplier['code'] ?? '')
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return 0;
        }

        return DB::table('product_attribute_values')
            ->whereIn('product_id', $productIds)
            ->count();
    }

    private function photosCount(array $supplier): int
    {
        $path = $supplier['image_disk_path'] ?? null;

        if (! $path) {
            return 0;
        }

        $dir = public_path($path);

        if (! is_dir($dir)) {
            return 0;
        }

        return count(glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []);
    }

    private function statusPath(): string
    {
        return storage_path('app/supplier-sync/status.json');
    }

    private function readStatus(string $key): array
    {
        $path = $this->statusPath();

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    private function writeStatus(string $key, array $status): void
    {
        $path = $this->statusPath();
        File::ensureDirectoryExists(dirname($path));

        $data = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $data = is_array($data) ? $data : [];
        $data[$key] = $status;

        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function writeLog(string $key, string $mode, int $exitCode, string $output): void
    {
        $dir = storage_path('logs/supplier-sync');
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/%s_%s_%s.log', $dir, $key, now()->format('Ymd_His'), $mode);

        file_put_contents($path, implode(PHP_EOL, [
            'Supplier: ' . $key,
            'Mode: ' . $mode,
            'Exit code: ' . $exitCode,
            'Run at: ' . now()->toDateTimeString(),
            str_repeat('-', 80),
            $output,
        ]));
    }

    private function lastLogPath(string $key): ?string
    {
        $files = glob(storage_path("logs/supplier-sync/{$key}_*.log")) ?: [];
        rsort($files);

        return $files[0] ?? null;
    }
}
