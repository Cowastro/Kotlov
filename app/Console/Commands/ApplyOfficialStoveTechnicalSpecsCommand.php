<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use App\Services\StoveOfficialTechnicalSpecCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyOfficialStoveTechnicalSpecsCommand extends Command
{
    private const HEATING_STOVE_CATEGORY_SLUGS = [
        'pechi-kaminy',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
    ];

    protected $signature = 'catalog:apply-official-stove-technical-specs
        {--apply : Persist manufacturer-published technical characteristics; the default is a dry run}';

    protected $description = 'Update selected stove characteristics verified in manufacturer pages and catalogues';

    public function handle(
        StoveOfficialTechnicalSpecCatalog $catalog,
        ProductSourceEnricher $enricher,
    ): int {
        $apply = (bool) $this->option('apply');
        $entries = $catalog->entries();
        $products = Product::query()
            ->whereIn('slug', array_keys($entries))
            ->with('category:id,slug')
            ->get()
            ->keyBy('slug');

        $plans = collect();
        $missing = [];
        $skipped = [];

        foreach ($entries as $slug => $evidence) {
            /** @var Product|null $product */
            $product = $products->get($slug);

            if (! $product) {
                $missing[] = $slug;

                continue;
            }

            if (! in_array($product->category?->slug, self::HEATING_STOVE_CATEGORY_SLUGS, true)
                || ! $product->is_active
                || $product->is_archived) {
                $skipped[] = [$product->id, $product->name, 'not an active heating stove product'];

                continue;
            }

            $updatedSpecs = $this->mergeSpecs($product->specs ?: [], $evidence['specs']);
            $plans->push(compact('product', 'evidence', 'updatedSpecs'));
        }

        $this->table(
            ['ID', 'Product', 'Verified fields', 'Official source'],
            $plans->map(function (array $plan): array {
                $fields = collect($plan['evidence']['specs'])->pluck('key');
                if (isset($plan['evidence']['product_name'])) {
                    $fields->prepend('Название, H1 и описание');
                }

                return [
                    $plan['product']->id,
                    $plan['product']->name,
                    $fields->implode(', '),
                    $plan['evidence']['source_url'],
                ];
            })->all()
        );

        if ($skipped !== []) {
            $this->warn('Skipped products:');
            $this->table(['ID', 'Product', 'Reason'], $skipped);
        }

        foreach ($missing as $slug) {
            $this->warn("Missing product: {$slug}");
        }

        $attributeValuesSaved = 0;

        if ($apply) {
            DB::transaction(function () use ($plans, $enricher, &$attributeValuesSaved): void {
                foreach ($plans as $plan) {
                    /** @var Product $product */
                    $product = $plan['product'];
                    $serviceInfo = is_array($product->service_info) ? $product->service_info : [];
                    $serviceInfo['official_technical_specs'] = [
                        'fields' => collect($plan['evidence']['specs'])->pluck('key')->values()->all(),
                        'source_url' => $plan['evidence']['source_url'],
                        'source_label' => $plan['evidence']['source_label'],
                        'verified_at' => now()->toDateString(),
                    ];

                    $updates = [
                        'specs' => $plan['updatedSpecs'],
                        'service_info' => $serviceInfo,
                    ];

                    foreach ([
                        'product_name' => 'name',
                        'h1' => 'h1',
                        'meta_title' => 'meta_title',
                        'meta_description' => 'meta_description',
                        'short_description' => 'short_description',
                        'content' => 'content',
                    ] as $evidenceKey => $productField) {
                        if (isset($plan['evidence'][$evidenceKey])) {
                            $updates[$productField] = $plan['evidence'][$evidenceKey];
                        }
                    }

                    $product->update($updates);

                    $this->removeConflictingAttributeValues($product, $plan['evidence']['specs']);
                    $attributeValuesSaved += $enricher->syncSpecsToAttributeValues(
                        $product,
                        $plan['evidence']['specs'],
                    );
                }
            });
        }

        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line(sprintf(
            'Evidence entries: %d; eligible products: %d; skipped: %d; missing: %d; attribute values saved: %d.',
            count($entries),
            $plans->count(),
            count($skipped),
            count($missing),
            $attributeValuesSaved,
        ));
        $this->line('Heated area was not added: the manufacturer pages do not publish it, and no kW conversion was made.');

        return ($missing === [] && $skipped === []) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int|string, mixed>  $existing
     * @param  array<int, array{key: string, value: string, unit: string}>  $verified
     * @return array<int|string, mixed>
     */
    private function mergeSpecs(array $existing, array $verified): array
    {
        $verifiedByKey = collect($verified)->keyBy(fn (array $spec) => $this->normalizeKey($spec['key']));
        $result = [];

        foreach ($existing as $key => $spec) {
            $existingKey = is_array($spec) ? (string) ($spec['key'] ?? '') : (is_string($key) ? $key : '');

            if ($existingKey !== '' && $verifiedByKey->has($this->normalizeKey($existingKey))) {
                continue;
            }

            $result[] = is_array($spec) ? $spec : [
                'key' => (string) $key,
                'value' => (string) $spec,
                'unit' => '',
            ];
        }

        return [...$result, ...array_values($verified)];
    }

    private function normalizeKey(string $key): string
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/[\s,._()%-]+/u', '', $key)));
        $normalized = str_replace(
            ['ш×г×в', 'шхгхв', 'шxгxв', 'в×ш×г', 'вхшхг', 'вxшxг'],
            ['шгв', 'шгв', 'шгв', 'вшг', 'вшг', 'вшг'],
            $normalized,
        );

        // Do this before removing unit suffixes: the abbreviation "л" must not
        // trim the last letter from the Russian word "материал".
        if (preg_match('/^(?:материал|материалкорпуса|материалтопки|облицовочныйматериал)$/u', $normalized)) {
            return 'материал';
        }

        $normalized = preg_replace('/(?:мм|кг|квт|литр(?:а|ов)?|л)$/u', '', $normalized) ?: $normalized;
        $normalized = preg_replace('/(?:шгв|вшг|дшв)$/u', '', $normalized) ?: $normalized;

        if (preg_match('/^(?:масса|вес|веснетто)$/u', $normalized)) {
            return 'масса';
        }

        if (preg_match('/^(?:мощность|тепловаямощность|номинальнаямощность|диапазонмощности)$/u', $normalized)) {
            return 'мощность';
        }

        if (preg_match('/^(?:габариты|размеры|размерыпечи)$/u', $normalized)) {
            return 'габариты';
        }

        if (preg_match('/^(?:размерытопки|размертопки|габаритытопки|размерыкамерысгорания|размерытопочнойкамеры)$/u', $normalized)) {
            return 'размерытопки';
        }

        if (preg_match('/^(?:мощностьводяногоконтура|мощностьпереданнаяводе|мощностьтеплообменника)$/u', $normalized)) {
            return 'мощностьводяногоконтура';
        }

        if (preg_match('/^(?:объёмводяногоконтура|объемводяногоконтура|объёмкотла|объемкотла)$/u', $normalized)) {
            return 'объёмводяногоконтура';
        }

        if (preg_match('/^(?:кпд|эффективность)$/u', $normalized)) {
            return 'кпд';
        }

        if (preg_match('/^(?:диаметрдымохода|диаметрдымоходногопатрубка|диаметрпатрубка)$/u', $normalized)) {
            return 'диаметрдымохода';
        }

        if (preg_match('/^(?:максимальнаядлинаполена|максимальнаядлинадров|длинаполена)$/u', $normalized)) {
            return 'максимальнаядлинаполена';
        }

        if (preg_match('/^(?:подключение|подключениедымохода|выходдымохода)$/u', $normalized)) {
            return 'подключениедымохода';
        }

        if (preg_match('/^(?:гарантия|гарантийныйсрок|гарантияпроизводителя)$/u', $normalized)) {
            return 'гарантия';
        }

        return $normalized;
    }

    /**
     * Remove product-level synonym rows before the canonical attributes are saved.
     * Attribute definitions themselves remain untouched because other products may use them.
     *
     * @param  array<int, array{key: string, value: string, unit: string}>  $verifiedSpecs
     */
    private function removeConflictingAttributeValues(Product $product, array $verifiedSpecs): void
    {
        $targetKeys = collect($verifiedSpecs)
            ->pluck('key')
            ->map(fn (string $key) => $this->normalizeKey($key))
            ->unique()
            ->all();

        $valueIds = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('pav.product_id', $product->id)
            ->get(['pav.id', 'a.name'])
            ->filter(fn (object $row) => in_array($this->normalizeKey((string) $row->name), $targetKeys, true))
            ->pluck('id')
            ->all();

        if ($valueIds !== []) {
            DB::table('product_attribute_values')->whereIn('id', $valueIds)->delete();
        }
    }
}
