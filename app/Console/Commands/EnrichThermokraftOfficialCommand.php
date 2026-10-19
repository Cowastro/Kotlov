<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichThermokraftOfficialCommand extends Command
{
    protected $signature = 'supplier:enrich-thermokraft-official
        {--apply : Write verified official data}
        {--limit=3 : Maximum cards per run}
        {--overwrite-images : Replace the current gallery with the official gallery}
        {--replace-specs : Replace characteristics with the official set}
        {--sleep=900 : Delay between official page requests, ms}';

    protected $description = 'Gradually enrich exact Thermokraft TENKO cards from official tenko.ua pages.';

    /**
     * Only exact, manually verified modifications belong here. Similar power alone
     * is not sufficient: voltage, thread and immersion length must also match.
     *
     * @var array<int,array{name:string,url:string,required:array<int,string>}>
     */
    private const VERIFIED = [
        [
            'name' => 'ТЭН трубчатый 4500 Вт/220В крепление гайка латунная 1,5" L=300мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-4500vt-kreplenie-gayka-latunnaya-15-l300mm',
            'required' => ['4500', '220', '1,5', '300'],
        ],
        [
            'name' => 'ТЭН трубчатый 9000 Вт/380В крепление гайка латунная 2" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-9000vt-kreplenie-gayka-latunnaya-380v-2-l420mm',
            'required' => ['9000', '380', '2', '420'],
        ],
        [
            'name' => 'ТЭН трубчатый 12000 Вт/380В крепление гайка латунная 2" L=420мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-1200vt-kreplenie-gayka-latunnaya-2-l-420-mm',
            'required' => ['12000', '380', '2', '420'],
        ],
        [
            'name' => 'ТЭН трубчатый 4500 Вт/220В крепление гайка латунная 1 1/4" L=300мм',
            'url' => 'https://tenko.ua/ru/catalog/good/blok-tenov-4500vt-kreplenie-gayka-latunnaya-114-l300mm',
            'required' => ['4500', '220', '11/4', '300'],
        ],
    ];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(300, (int) $this->option('sleep'));
        $stats = ['candidates' => 0, 'verified' => 0, 'enriched' => 0, 'images' => 0, 'specs' => 0, 'skipped' => 0, 'errors' => 0];

        $rows = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('s.code', 'thermokraft')
            ->where('p.is_archived', false)
            ->select('sp.id as supplier_product_id', 'sp.source_url', 'p.id as product_id', 'p.name')
            ->get()
            ->keyBy(fn ($row): string => $this->key((string) $row->name));

        foreach (self::VERIFIED as $rule) {
            if ($stats['verified'] >= $limit) {
                break;
            }

            $row = $rows->get($this->key($rule['name']));
            if (! $row) {
                $stats['skipped']++;
                continue;
            }
            if (str_contains((string) $row->source_url, 'tenko.ua/')) {
                $stats['skipped']++;
                continue;
            }

            $stats['candidates']++;
            try {
                $parsed = $enricher->preview($rule['url']);
                if (! $this->matchesOfficialCard((string) ($parsed['title'] ?? ''), $parsed['specs'] ?? [], $rule['required'])) {
                    $stats['errors']++;
                    $this->warn('Official card signature mismatch for product #'.(int) $row->product_id);
                    continue;
                }

                $stats['verified']++;
                $stats['images'] += count($parsed['images'] ?? []);
                $stats['specs'] += count($parsed['specs'] ?? []);

                if ($apply) {
                    $product = Product::query()->findOrFail((int) $row->product_id);
                    $result = $enricher->enrichFromParsed($product, $rule['url'], $parsed, [
                        'replace_images' => (bool) $this->option('overwrite-images'),
                        'update_images' => true,
                        'update_specs' => true,
                        'replace_specs' => (bool) $this->option('replace-specs'),
                        'min_specs_to_replace' => 4,
                        'update_service' => true,
                        'update_documents' => true,
                        'update_video' => true,
                        'update_content' => false,
                    ]);
                    DB::table('supplier_products')->where('id', (int) $row->supplier_product_id)->update([
                        'source_url' => $rule['url'],
                        'updated_at' => now(),
                    ]);
                    if (($result['updated_fields'] ?? []) !== []) {
                        $stats['enriched']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('Product #'.(int) $row->product_id.': '.$e->getMessage());
            }

            usleep($sleep * 1000);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.thermokraft_official.{$key}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<int,array<string,mixed>> $specs @param array<int,string> $required */
    private function matchesOfficialCard(string $title, array $specs, array $required): bool
    {
        $haystack = $this->key($title.' '.collect($specs)->map(fn ($spec) => ($spec['key'] ?? '').' '.($spec['value'] ?? ''))->implode(' '));

        foreach ($required as $value) {
            if (! str_contains($haystack, $this->key($value))) {
                return false;
            }
        }

        return $haystack !== '';
    }

    private function key(string $value): string
    {
        $value = mb_strtoupper(str_replace(['Ё', 'MM', 'KW'], ['Е', 'ММ', 'КВТ'], $value));

        return preg_replace('/[^A-ZА-Я0-9]+/u', '', $value) ?? '';
    }
}
