<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

class EnrichTmManagementWorkbookCommand extends Command
{
    protected $signature = 'supplier:enrich-tm-workbook
        {--apply : Save images, descriptions and specifications}
        {--brand= : Process one brand}
        {--download= : Use a local XLSX file}
        {--replace-images : Replace an existing product gallery}
        {--limit= : Limit matched product rows}';

    protected $description = 'Enrich TM Management products from descriptions and embedded images in the full workbook.';

    private const SUPPLIER_CODE = 'tm-management';
    private const XLSX_URL = 'https://docs.google.com/spreadsheets/d/1yVThZ3ZbL6dBqzpxxsUCtF-8N32lQtHK/export?format=xlsx';

    private const SHEETS = [
        'De Dietrich' => ['sheet' => ' De Dietrich РЦ BYN 25.05', 'article' => 1, 'name' => 2, 'description' => null, 'specs' => null, 'price' => 8],
        'Shinhoo' => ['sheet' => ' Shinhoo Vanjord 03.03', 'article' => 2, 'name' => 3, 'description' => 5, 'specs' => 4, 'price' => 8],
        'SFA' => ['sheet' => ' SFA 13.04', 'article' => 4, 'name' => 4, 'description' => 5, 'specs' => 6, 'price' => 9],
        'Watrix' => ['sheet' => 'Watrix', 'article' => 2, 'name' => 3, 'description' => 4, 'specs' => 4, 'price' => 6],
    ];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $onlyBrand = trim((string) $this->option('brand'));
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $path = $this->workbookPath(trim((string) $this->option('download')));
        $configs = collect(self::SHEETS)
            ->when($onlyBrand !== '', fn ($items) => $items->filter(fn ($config, $brand) => mb_strtolower($brand) === mb_strtolower($onlyBrand)));

        if ($configs->isEmpty()) {
            $this->error('Unknown brand: '.$onlyBrand);
            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->error('TM Management supplier not found.');
            return self::FAILURE;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly($configs->pluck('sheet')->all());
        $workbook = $reader->load($path);
        $links = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('product_id')
            ->get();
        $index = $this->linkIndex($links);
        $stats = ['rows' => 0, 'matched' => 0, 'skipped' => 0, 'ambiguous' => 0, 'images_saved' => 0, 'content_saved' => 0, 'specs_saved' => 0, 'attribute_sync_errors' => 0, 'errors_load' => 0, 'errors_specs' => 0, 'errors_content' => 0, 'errors_images' => 0, 'errors_save' => 0, 'errors' => 0];

        foreach ($configs as $brand => $config) {
            $sheet = $workbook->getSheetByName($config['sheet']);
            if (! $sheet) {
                continue;
            }

            $imagesByRow = $this->imagesByRow($sheet);
            $highestRow = $sheet->getHighestDataRow();
            for ($row = 1; $row <= $highestRow; $row++) {
                $article = $this->oneLine($sheet->getCell([$config['article'], $row])->getFormattedValue());
                $name = $this->oneLine($sheet->getCell([$config['name'], $row])->getFormattedValue());
                $price = $this->number($sheet->getCell([$config['price'], $row])->getFormattedValue());
                if (! $this->isProductRow($brand, $article, $name, $price)) {
                    continue;
                }

                $stats['rows']++;
                $productId = $this->matchedProductId($index, $article, $name);
                if ($productId === null) {
                    $stats['skipped']++;
                    continue;
                }
                if ($productId === false) {
                    $stats['ambiguous']++;
                    continue;
                }

                $stats['matched']++;
                if ($limit !== null && $stats['matched'] > $limit) {
                    break 2;
                }
                if (! $apply) {
                    continue;
                }

                $stage = 'load';
                try {
                    $product = Product::query()->find($productId);
                    if (! $product || $product->is_archived) {
                        $stats['skipped']++;
                        continue;
                    }

                    $description = $config['description']
                        ? trim((string) $sheet->getCell([$config['description'], $row])->getValue())
                        : '';
                    $technical = $config['specs']
                        ? trim((string) $sheet->getCell([$config['specs'], $row])->getValue())
                        : '';
                    $stage = 'specs';
                    $specs = $this->sourceSpecs($brand, $description, $technical);

                    if ($specs !== []) {
                        $merged = $this->mergeSpecs($product->specs, $specs);
                        $product->specs = $merged;
                        try {
                            $enricher->syncSpecsToAttributeValues($product, $merged);
                        } catch (\Throwable $e) {
                            // Keep the source facts on the product even when a legacy
                            // attribute definition cannot accept one unusual value.
                            $stats['attribute_sync_errors']++;
                            report($e);
                        }
                        $stats['specs_saved']++;
                    }

                    $stage = 'content';
                    if ($description !== '' && $this->needsSourceContent($product)) {
                        $product->content = $this->content($product->name, $brand, $description, $specs);
                        $product->short_description = Str::limit($this->oneLine($description), 245, '');
                        $stats['content_saved']++;
                    }

                    $stage = 'images';
                    $images = $imagesByRow[$row] ?? [];
                    if ($images !== [] && ((bool) $this->option('replace-images') || $this->imagesEmpty($product->images))) {
                        $saved = $this->saveImages($product, $brand, $images);
                        if ($saved !== []) {
                            $product->images = $saved;
                            $stats['images_saved']++;
                        }
                    }

                    $stage = 'save';
                    $product->save();
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stats['errors_'.$stage]++;
                    $this->warn('Product '.$productId.': '.$e->getMessage());
                }
            }
        }

        foreach ($stats as $key => $value) {
            $this->line('metric.tm_workbook.'.$key.'='.$value);
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function workbookPath(string $local): string
    {
        if ($local !== '') {
            if (! is_file($local)) {
                throw new \RuntimeException('Workbook not found: '.$local);
            }
            return $local;
        }

        $path = storage_path('app/tmp/tm-management-full.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $response = Http::timeout(180)->retry(3, 1000)->get(self::XLSX_URL);
        if (! $response->successful() || strlen($response->body()) < 100000) {
            throw new \RuntimeException('Could not download TM Management workbook.');
        }
        file_put_contents($path, $response->body());

        return $path;
    }

    /** @return array{article:array<string,array<int,int>>,name:array<string,array<int,int>>} */
    private function linkIndex($links): array
    {
        $index = ['article' => [], 'name' => []];
        foreach ($links as $link) {
            $article = $this->normalize((string) $link->supplier_article);
            $name = $this->normalize((string) $link->supplier_name);
            if ($article !== '') {
                $index['article'][$article][] = (int) $link->product_id;
            }
            if ($name !== '') {
                $index['name'][$name][] = (int) $link->product_id;
            }
        }
        return $index;
    }

    private function matchedProductId(array $index, string $article, string $name): int|false|null
    {
        foreach ([['article', $article], ['name', $name]] as [$group, $value]) {
            $ids = array_values(array_unique($index[$group][$this->normalize($value)] ?? []));
            if (count($ids) === 1) {
                return $ids[0];
            }
            if (count($ids) > 1) {
                return false;
            }
        }
        return null;
    }

    private function isProductRow(string $brand, string $article, string $name, ?float $price): bool
    {
        if ($name === '' || $price === null || $price <= 0) {
            return false;
        }
        if ($brand === 'SFA') {
            return mb_strlen($name) >= 3;
        }
        return $article !== '' && preg_match('/[\pL\pN]{2,}/u', $article) === 1;
    }

    /** @return array<int,array{data:string,mime:string,width:int,height:int}> */
    private function imagesByRow($sheet): array
    {
        $byRow = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinate = $drawing->getCoordinates();
            [, $row] = Coordinate::coordinateFromString($coordinate);
            $rows = [(int) $row];
            foreach ($sheet->getMergeCells() as $range) {
                if (! $sheet->getCell($coordinate)->isInRange($range)) {
                    continue;
                }
                [, $startRow] = Coordinate::coordinateFromString(explode(':', $range)[0]);
                [, $endRow] = Coordinate::coordinateFromString(explode(':', $range)[1]);
                $rows = range((int) $startRow, (int) $endRow);
                break;
            }

            $image = $this->drawingData($drawing);
            if ($image === null || $image['width'] < 120 || $image['height'] < 120) {
                continue;
            }
            foreach ($rows as $imageRow) {
                $byRow[$imageRow][md5($image['data'])] = $image;
            }
        }

        return array_map('array_values', $byRow);
    }

    private function drawingData($drawing): ?array
    {
        if ($drawing instanceof MemoryDrawing) {
            ob_start();
            ($drawing->getRenderingFunction())($drawing->getImageResource());
            $data = (string) ob_get_clean();
        } else {
            $data = (string) @file_get_contents($drawing->getPath());
        }
        $size = @getimagesizefromstring($data);
        if (! $size) {
            return null;
        }
        return ['data' => $data, 'mime' => (string) ($size['mime'] ?? ''), 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    /** @return list<string> */
    private function saveImages(Product $product, string $brand, array $images): array
    {
        $dir = 'img/products/tm-management/'.Str::slug($brand);
        $absolute = public_path($dir);
        if (! is_dir($absolute)) {
            mkdir($absolute, 0755, true);
        }
        $paths = [];
        foreach (array_slice($images, 0, 6) as $image) {
            $ext = match ($image['mime']) {
                'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg',
            };
            $file = $product->id.'-'.substr(md5($image['data']), 0, 10).'.'.$ext;
            file_put_contents($absolute.DIRECTORY_SEPARATOR.$file, $image['data']);
            $paths[] = $dir.'/'.$file;
        }
        return $paths;
    }

    private function sourceSpecs(string $brand, string $description, string $technical): array
    {
        $specs = [];
        if ($brand === 'Shinhoo' && $technical !== '') {
            $specs['Максимальная потребляемая мощность'] = $this->oneLine($technical);
        }
        $source = $brand === 'Watrix' ? $description : $technical;
        foreach (preg_split('/\R+/u', $source) ?: [] as $line) {
            $line = trim($line, " \t\n\r\0\x0B•");
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*(?:[:\-–]\s+|\s+)(IP\d+|Н\/Д|\d.*)$/u', $line, $match) !== 1) {
                continue;
            }
            $key = trim($match[1], " :-–");
            $value = trim($match[2]);
            if ($key !== '' && mb_strlen($key) <= 100 && mb_strlen($value) <= 250) {
                $specs[$key] = $value;
            }
        }
        return $specs;
    }

    private function mergeSpecs(mixed $existing, array $source): array
    {
        $decoded = is_array($existing) ? $existing : json_decode((string) $existing, true);
        $byKey = [];
        foreach (is_array($decoded) ? $decoded : [] as $key => $spec) {
            $name = is_array($spec) ? (string) ($spec['key'] ?? $spec['name'] ?? '') : (is_string($key) ? $key : '');
            $value = is_array($spec) ? (string) ($spec['value'] ?? '') : (string) $spec;
            if ($name !== '') {
                $byKey[mb_strtolower($name)] = ['key' => $name, 'value' => $value, 'unit' => is_array($spec) ? (string) ($spec['unit'] ?? '') : ''];
            }
        }
        foreach ($source as $key => $value) {
            $byKey[mb_strtolower($key)] = ['key' => $key, 'value' => $value, 'unit' => ''];
        }
        return array_values($byKey);
    }

    private function needsSourceContent(Product $product): bool
    {
        $content = trim((string) $product->content);
        return $content === '' || (str_contains($content, 'товар бренда') && str_contains($content, 'Что уточнить перед покупкой'));
    }

    private function content(string $name, string $brand, string $description, array $specs): string
    {
        $facts = collect($specs)->take(8)->map(fn ($value, $key) => '<li><strong>'.e($key).':</strong> '.e($value).'</li>')->implode('');
        $factsHtml = $facts !== '' ? '<h3>Основные характеристики</h3><ul>'.$facts.'</ul>' : '';
        return '<p><strong>'.e($name).'</strong> — оборудование '.e($brand).'. '.nl2br(e(Str::limit(trim($description), 1800, ''))).'</p>'.$factsHtml
            .'<p>Перед заказом специалисты KOTLOV.BY помогут проверить совместимость, комплектацию и условия монтажа в %city%.</p>';
    }

    private function imagesEmpty(mixed $images): bool
    {
        if (is_array($images)) {
            return $images === [];
        }
        $decoded = json_decode((string) $images, true);
        return ! is_array($decoded) || $decoded === [];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $value) ?? '');
    }

    private function oneLine(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private function number(mixed $value): ?float
    {
        $text = str_replace(["\xc2\xa0", ' '], '', trim((string) $value));
        $text = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $text) ?? '');
        return is_numeric($text) ? (float) $text : null;
    }
}
