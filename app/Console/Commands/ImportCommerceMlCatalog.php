<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Integrations\CommerceMlCatalogImporter;
use Illuminate\Console\Command;

class ImportCommerceMlCatalog extends Command
{
    protected $signature = 'integration:import-commerceml
                            {file : Path to a CommerceML XML file}
                            {--source=onec : Integration source code}';

    protected $description = 'Import CommerceML into the safe matching buffer without changing storefront products.';

    public function handle(CommerceMlCatalogImporter $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Файл не найден или недоступен: {$path}");

            return self::FAILURE;
        }

        $sourceCode = (string) $this->option('source');
        $productCountBefore = Product::query()->count();
        $stats = $importer->import((string) file_get_contents($path), $sourceCode);
        $productCountAfter = Product::query()->count();
        $source = IntegrationSource::query()->where('code', $sourceCode)->firstOrFail();

        $this->newLine();
        $this->info('CommerceML загружен в буфер сопоставления.');
        $this->table(['Показатель', 'Количество'], [
            ['Позиций в файле', $stats['products']],
            ['Предложений/остатков', $stats['offers']],
            ['Автоматически привязано', $stats['matched']],
            ['Предложено на проверку', $stats['suggested']],
            ['Несколько вариантов', $stats['ambiguous']],
            ['Не найдено', $stats['unmatched']],
            ['Всего записей источника', IntegrationProduct::query()->whereBelongsTo($source, 'source')->count()],
        ]);

        if ($productCountBefore !== $productCountAfter) {
            $this->error('Защитная проверка не пройдена: изменилось количество карточек сайта.');

            return self::FAILURE;
        }

        $this->line('Карточки сайта не изменялись. Проверьте пары в админке: /admin/integration-products');

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
