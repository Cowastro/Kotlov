<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;

class InspectIntegrationSourceCommand extends Command
{
    protected $signature = 'integration:inspect-source {source}';

    protected $description = 'Show non-sensitive staging and CommerceML file statistics for an integration source';

    public function handle(): int
    {
        $source = IntegrationSource::query()->where('code', $this->argument('source'))->first();
        if (! $source) {
            $this->error('Integration source not found.');

            return self::FAILURE;
        }

        $query = IntegrationProduct::query()->whereBelongsTo($source, 'source');
        $this->table(['Metric', 'Count'], [
            ['Staged products', (clone $query)->count()],
            ['Positive stock', (clone $query)->where('stock_quantity', '>', 0)->count()],
            ['Zero stock', (clone $query)->where('stock_quantity', 0)->count()],
            ['Missing stock', (clone $query)->whereNull('stock_quantity')->count()],
            ['With price', (clone $query)->whereNotNull('price')->count()],
        ]);

        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));
        $basePath = trim((string) config('onec.exchange.storage_path'), '/').'/'.$source->code;
        $rows = [];

        foreach ($disk->allFiles($basePath) as $path) {
            if (! preg_match('/\.xml$/i', $path)) {
                continue;
            }

            $contents = $disk->get($path);
            $document = @simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            $rows[] = [
                basename($path),
                strlen($contents),
                $document === false ? 'no' : 'yes',
                $document === false ? '—' : count($document->xpath('//*[local-name()="Товар"]') ?: []),
                $document === false ? '—' : count($document->xpath('//*[local-name()="Предложение"]') ?: []),
                $document === false ? '—' : count($document->xpath('//*[local-name()="Количество"]') ?: []),
            ];
        }

        $this->table(['File', 'Bytes', 'Valid XML', 'Products', 'Offers', 'Quantities'], $rows);

        return self::SUCCESS;
    }
}
