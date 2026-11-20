<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use App\Services\Suppliers\AkvateploservisPriceList;
use App\Services\Suppliers\FirewayTeplodvorCatalog;
use App\Services\Suppliers\TermTeploModel;
use App\Services\Suppliers\TermTeploMarketplaceLinks;
use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class SyncDealByBrandPricesCommand extends Command
{
    private const PAGE_SIZE = 100;

    private const SUPPORTED_SUPPLIERS = ['bania', 'prometall', 'kaminproff', 'akvateploservis', 'teplov', 'metabel', 'termteplosistem'];

    /** Galmet and Apamet goods of the shop by their name (Акватеплосервис). */
    private const GALMET_NAME = '/galmet|apamet|галмет|апамет/ui';

    /** Мета-Бел goods on the shop: «Печь-камин Мета-Бел Ока 6 кВт», «Печь-камин Селенга 7 кВт (Мета-Бел)». */
    private const META_NAME = '/мета[\s-]*бел/ui';

    /** Shop items found by the model of the name, linked by hand with --pair. */
    private const MODEL_SUPPLIERS = ['kaminproff', 'akvateploservis', 'metabel'];

    /** Fireway goods of the shop by their name: no articles, the model decides (Камин-профессионал). */
    private const FIREWAY_NAME = '/fire\s*way|файрвей|паровар|alma/ui';

    private ?string $supplierCode = null;

    protected $signature = 'deal:sync-brand-prices
        {--supplier= : Audit products linked to an allowed supplier code instead of the default brands}
        {--review-unmatched : Показать по каждой непривязанной карточке кандидатов SBG для проверки}
        {--inspect-id= : Emit safe price and discount fields for one Deal.by product ID}
        {--pair=* : Fireway: a shop item linked by hand, deal id or the slug of its name:card slug (kept on the offer with --apply)}
        {--export-missing= : Fireway: a deal.by import xlsx of the cards missing on the shop, a file name under public/exports}
        {--export-cards= : With --export-missing: export these card ids (comma separated) instead of the missing ones, e.g. to resend photos}
        {--apply : Update matched Deal.by product prices}';

    /** @var array<int, int> deal.by id => card id, linked by hand */
    private array $dealPairs = [];

    protected $description = 'Safely audit or synchronize selected KOTLOV.BY prices to Deal.by';

    public function handle(DealByClient $client): int
    {
        try {
            $this->supplierCode = $this->resolveSupplierCode();
            $localProducts = $this->targetProducts();
            $remoteProducts = $this->loadRemoteProducts($client);
            if ($this->supplierCode === 'termteplosistem' && $remoteProducts->isEmpty()) {
                throw new RuntimeException('Каталог SBG пуст. Сохранённые привязки оставлены для следующей сверки.');
            }

            if (filled($this->option('inspect-id'))) {
                return $this->inspectRemoteProduct($remoteProducts, (int) $this->option('inspect-id'));
            }

            $this->dealPairs = $this->dealPairs($localProducts, $remoteProducts);
            $plan = $this->buildPlan($localProducts, $remoteProducts);

            $this->metrics($localProducts, $remoteProducts, $plan);
            if ($this->supplierCode === 'termteplosistem') {
                $offers = $plan['supplier_offers'];
                $links = $plan['supplier_links'];
                $this->line('metric.deal_brand_prices.supplier_links='.count($links));
                $this->line('metric.deal_brand_prices.links_without_active_card='.$offers->whereIn('id', array_keys($links))->whereNotIn('product_id', $localProducts->pluck('id'))->count());
                if ($this->option('apply')) {
                    DB::transaction(function () use ($offers, $links) {
                        foreach ($offers as $offer) {
                            $id = $links[$offer->id] ?? null;
                            if (($offer->raw['sbg_catalog_id'] ?? null) !== $id) {
                                SupplierProduct::whereKey($offer->id)->update(['raw->sbg_catalog_id' => $id]);
                            }
                        }
                    });
                }
            }
            if ($this->option('review-unmatched') && $this->supplierCode === 'termteplosistem') {
                $available = $remoteProducts->whereNotIn('id', $plan['matched_remote_ids']);
                $families = $available->groupBy(fn ($item) => TermTeploModel::family($item['name'] ?? '') ?? '')->forget('');
                foreach ($localProducts->whereNotIn('id', $plan['matched_local_ids']) as $card) {
                    $family = TermTeploModel::family($card->name, $card->brand?->name ?? '');
                    $ids = $family ? $families->get($family, collect())->pluck('id')->implode(',') : '';
                    $this->line("audit.termteplo.review={$card->id}:".($ids ?: 'none'));
                }
            }
            if (filled($this->option('export-missing'))) {
                $cards = array_filter(array_map('intval', explode(',', (string) $this->option('export-cards'))));
                // Listed cards are exported whatever their brand or supplier (a one-off: new goods of our own stock).
                $this->exportMissing($cards !== [] ? Product::query()->whereIn('id', $cards)->get()
                    : $localProducts->whereNotIn('id', $plan['matched_local_ids'])->values(), (string) $this->option('export-missing'));
            }

            if ($this->supplierCode === null) {
                $localVezuviy = $localProducts->filter(
                    fn (Product $product): bool => $this->normalize((string) $product->brand?->name) === 'везувий'
                )->count();
                $localTatra = $localProducts->count() - $localVezuviy;
                if ($localVezuviy === 0 || $localTatra === 0) {
                    throw new RuntimeException('Безопасная остановка: в рабочей базе не найдена одна из целевых групп товаров.');
                }
            } elseif ($localProducts->isEmpty() && ! $plan['supplier_links']) {
                throw new RuntimeException('Безопасная остановка: у поставщика нет активных товаров с ценой.');
            }

            if (! $this->option('apply')) {
                $this->info('Проверка завершена. Deal.by не изменялся.');

                return self::SUCCESS;
            }

            if ($this->supplierCode === 'termteplosistem' && $plan['supplier_links'] && $plan['matched'] === 0) {
                $this->info('Связи предложений сохранены. Активных пар для обновления цен пока нет.');

                return self::SUCCESS;
            }
            if ($plan['matched'] === 0) {
                throw new RuntimeException('Безопасная остановка: ни один целевой товар не связан с Deal.by.');
            }

            foreach ($plan['updates']->chunk(self::PAGE_SIZE) as $chunk) {
                $response = $client->editProducts($chunk->map(fn (array $item): array => [
                    'id' => $item['deal_id'],
                    'price' => $item['new_price'],
                    ...($item['stock_update'] ?? []),
                ])->values()->all());

                if (filled($response['error'] ?? null) || collect($response['errors'] ?? [])->filter()->isNotEmpty()) {
                    throw new RuntimeException('Deal.by отклонил обновление цен.');
                }
            }

            $verification = $this->buildPlan($localProducts, $this->loadRemoteProducts($client));
            $remaining = $verification['updates']->count();

            $this->line('metric.deal_brand_prices.updated='.$plan['updates']->count());
            $this->line('metric.deal_brand_prices.remaining_mismatches='.$remaining);

            if ($remaining > 0) {
                throw new RuntimeException('Повторная проверка Deal.by обнаружила оставшиеся расхождения цен.');
            }

            $this->info($this->supplierCode === null
                ? 'Цены «Везувий» и котлов Tatra обновлены и проверены на Deal.by.'
                : 'Цены товаров поставщика обновлены и проверены на Deal.by.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Синхронизация цен Deal.by остановлена: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return Collection<int, Product> */
    private function targetProducts(): Collection
    {
        $query = Product::query()
            ->with(['brand:id,name', 'supplierProducts:id,product_id,supplier_id,supplier_article'])
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->where('price', '>', 0);

        if ($this->supplierCode !== null) {
            $query->whereHas('supplierProducts.supplier', function ($supplier): void {
                $supplier->where('code', $this->supplierCode)->where('is_active', true);
            });
        }

        $products = $query->get(['id', 'brand_id', 'name', 'sku', 'slug', 'price', 'stock_qty', 'in_stock']);

        if ($this->supplierCode !== null) {
            return $products->values();
        }

        return $products
            ->filter(function (Product $product): bool {
                $brand = $this->normalize((string) $product->brand?->name);

                if ($brand === 'везувий') {
                    return true;
                }

                return in_array($brand, ['tatra', 'татра'], true);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $localProducts
     * @param  Collection<int, array<string, mixed>>  $remoteProducts
     * @return array{updates: Collection<int, array<string, mixed>>, matched: int, unchanged: int, unmatched: int, ambiguous: int, invalid_remote_price: int}
     */
    private function buildPlan(Collection $localProducts, Collection $remoteProducts): array
    {
        $localById = $localProducts->keyBy('id');
        // The shop keeps the manufacturer's article (ProMetall p0048) as its SKU: the supplier's article of the card.
        $supplierId = $this->supplierCode !== null
            ? (int) DB::table('suppliers')->where('code', $this->supplierCode)->value('id')
            : 0;
        $termOffers = $this->supplierCode === 'termteplosistem'
            ? SupplierProduct::where('supplier_id', $supplierId)->get() : collect();
        $termByProduct = $termOffers->groupBy('product_id');
        $termLinks = $termOffers->isEmpty() ? [] : (new TermTeploMarketplaceLinks)->resolve(
            $termOffers->reject(fn ($offer) => $offer->stock_status === 'missing_from_feed'), $remoteProducts
        );
        $termByRemoteId = $termOffers->whereIn('id', array_keys($termLinks))->keyBy(fn ($offer) => $termLinks[$offer->id]);
        $localByArticle = $supplierId > 0
            ? $localProducts->flatMap(fn (Product $product) => $product->supplierProducts
                ->where('supplier_id', $supplierId)
                ->map(fn ($offer) => [$this->normalizeSku((string) $offer->supplier_article), $product]))
                ->filter(fn (array $pair): bool => $pair[0] !== '')
                ->groupBy(fn (array $pair): string => $pair[0], true)
                ->map(fn ($pairs) => collect($pairs)->map(fn (array $pair) => $pair[1])->unique('id')->values())
            : collect();
        $localBySku = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeSku($product->sku) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->sku));
        $localBySlug = $localProducts
            ->filter(fn (Product $product): bool => filled($product->slug))
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->slug));
        $localByName = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeName($product->name) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeName($product->name));
        $localByTatraModel = $localProducts
            ->filter(fn (Product $product): bool => $this->isTatra($product))
            ->mapToGroups(fn (Product $product): array => [
                $this->tatraModelKey(
                    $product->name,
                    $product->slug,
                    $product->sku,
                    ...$product->supplierProducts->pluck('supplier_article')->all(),
                ) ?? '' => $product,
            ])
            ->forget('');
        // Fireway (Камин-профессионал) has no articles, Galmet shop items often lack them: the shop item and the
        // card meet by the model of the name.
        $fireway = in_array($this->supplierCode, self::MODEL_SUPPLIERS, true);
        $namePattern = match ($this->supplierCode) {
            'akvateploservis' => self::GALMET_NAME,
            'metabel' => self::META_NAME,
            default => self::FIREWAY_NAME,
        };
        $localByModel = $fireway
            ? $localProducts->flatMap(fn (Product $product) => collect([$product->name])
                ->concat($product->supplierProducts->where('supplier_id', $supplierId)->pluck('supplier_name'))
                ->flatMap(fn ($name) => $this->modelKeys((string) $name))
                ->unique()
                ->map(fn (string $key) => [$key, $product]))
                ->groupBy(fn (array $pair): string => $pair[0], true)
                ->map(fn ($pairs) => collect($pairs)->map(fn (array $pair) => $pair[1])->unique('id')->values())
            : collect();
        $remoteModelCounts = $fireway
            ? $remoteProducts->filter(fn (array $product): bool => preg_match($namePattern, (string) ($product['name'] ?? '')) === 1)
                ->flatMap(fn (array $product) => $this->modelKeys((string) ($product['name'] ?? '')))
                ->countBy()
            : collect();
        $remoteSkuCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeSku($product['sku'] ?? null))
            ->filter()
            ->countBy();
        $remoteNameCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeName($product['name'] ?? null))
            ->filter()
            ->countBy();
        $remoteTatraModelCounts = $remoteProducts
            ->filter(fn (array $product): bool => $this->looksLikeTatra($product['name'] ?? null))
            ->map(fn (array $product): ?string => $this->tatraModelKey(
                $product['name'] ?? null,
                $product['sku'] ?? null,
            ))
            ->filter()
            ->countBy();

        $updates = collect();
        $matched = 0;
        $unchanged = 0;
        $ambiguous = 0;
        $invalidRemotePrice = 0;
        $matchedLocalIds = collect();
        $matchedRemoteIds = collect();

        foreach ($remoteProducts as $remote) {
            $local = null;
            $externalId = trim((string) ($remote['external_id'] ?? ''));
            $remoteSku = $this->normalizeSku($remote['sku'] ?? null);
            if ($this->supplierCode === 'termteplosistem' && $remoteSku !== ''
                && TermTeploModel::article((string) $remote['sku'], $remote['name'] ?? '') !== (string) $remote['sku']) {
                $ambiguous++;

                continue;
            }

            if (preg_match('/^kotlov-(\d+)$/i', $externalId, $match) === 1) {
                $local = $localById->get((int) $match[1]);
                if ($this->supplierCode === 'termteplosistem' && ! $local) {
                    continue;
                }
            }
            if (! $local && isset($this->dealPairs[(int) ($remote['id'] ?? 0)])) {
                $local = $localById->get($this->dealPairs[(int) $remote['id']]);
            }

            if (! $local && $remoteSku !== '' && ($remoteSkuCounts[$remoteSku] ?? 0) === 1) {
                $slugCandidates = $localBySlug->get($remoteSku, collect());
                $skuCandidates = $localBySku->get($remoteSku, collect());
                $articleCandidates = $localByArticle->get($remoteSku, collect());
                $candidates = $slugCandidates->concat($skuCandidates)->concat($articleCandidates)->unique('id')->values();

                if ($candidates->count() === 1) {
                    $local = $candidates->first();
                } elseif ($candidates->count() > 1) {
                    $ambiguous++;
                }
            }

            $remoteName = $this->normalizeName($remote['name'] ?? null);
            if (! $local && $this->supplierCode === 'termteplosistem' && $externalId === '') {
                $local = $localById->get($termByRemoteId->get($remote['id'] ?? null)?->product_id);
            }
            if (! $local && $this->supplierCode !== 'termteplosistem' && $remoteName !== '' && ($remoteNameCounts[$remoteName] ?? 0) === 1) {
                $nameCandidates = $localByName->get($remoteName, collect());
                if ($nameCandidates->count() === 1) {
                    $local = $nameCandidates->first();
                } elseif ($nameCandidates->count() > 1) {
                    $ambiguous++;
                }
            }

            if (! $local && $fireway && preg_match($namePattern, (string) ($remote['name'] ?? '')) === 1) {
                foreach ($this->modelKeys((string) $remote['name']) as $key) {
                    $modelCandidates = $localByModel->get($key, collect());
                    if (($remoteModelCounts[$key] ?? 0) === 1 && $modelCandidates->count() === 1) {
                        $local = $modelCandidates->first();
                        break;
                    }
                    if ($modelCandidates->count() > 1) {
                        $ambiguous++;
                        break;
                    }
                }
            }

            if (! $local && $this->supplierCode !== 'termteplosistem' && $this->looksLikeTatra($remote['name'] ?? null)) {
                $model = $this->tatraModelKey($remote['name'] ?? null, $remote['sku'] ?? null);
                $modelCandidates = $model !== null ? $localByTatraModel->get($model, collect()) : collect();

                if ($model !== null && ($remoteTatraModelCounts[$model] ?? 0) === 1 && $modelCandidates->count() === 1) {
                    $local = $modelCandidates->first();
                } elseif ($modelCandidates->count() > 1) {
                    $ambiguous++;
                }
            }

            if (! $local) {
                continue;
            }

            $matched++;
            $matchedLocalIds->push($local->id);
            $matchedRemoteIds->push((int) ($remote['id'] ?? 0));

            if (! is_numeric($remote['price'] ?? null)) {
                $invalidRemotePrice++;

                continue;
            }

            $remotePrice = round((float) $remote['price'], 2);
            $localPrice = round((float) $local->price, 2);
            $stockUpdate = [];
            if ($this->supplierCode === 'termteplosistem') {
                $offers = $termByProduct->get($local->id, collect());
                $offer = $offers->count() === 1 ? $offers->first() : null;
                if ($offer && $offer->last_stock_synced_at?->gte(now()->subDay()) && $offer->stock_quantity !== null
                    && $local->stock_qty !== null && (int) $local->stock_qty === $offer->stock_quantity
                    && (bool) $local->in_stock === ($offer->stock_quantity > 0)) {
                    $presence = $offer->stock_quantity > 0 ? 'available' : 'not_available';
                    if (($remote['presence'] ?? '') !== $presence || ! is_numeric($remote['quantity_in_stock'] ?? null)
                        || (int) $remote['quantity_in_stock'] !== $offer->stock_quantity) {
                        $stockUpdate = ['presence' => $presence, 'quantity_in_stock' => $offer->stock_quantity];
                    }
                }
            }
            if (abs($remotePrice - $localPrice) < 0.01 && ! $stockUpdate) {
                $unchanged++;

                continue;
            }

            $group = $this->supplierCode
                ?? ($this->normalize((string) $local->brand?->name) === 'везувий' ? 'vezuviy' : 'tatra_boilers');
            $updates->push([
                'deal_id' => (int) ($remote['id'] ?? 0),
                'local_id' => (int) $local->id,
                'group' => $group,
                'old_price' => $remotePrice,
                'new_price' => $localPrice,
                'stock_update' => $stockUpdate,
            ]);
        }

        if ($updates->contains(fn (array $item): bool => $item['deal_id'] <= 0)) {
            throw new RuntimeException('Deal.by вернул товар без корректного ID.');
        }

        return [
            'updates' => $updates,
            'matched' => $matched,
            'unchanged' => $unchanged,
            'unmatched' => $localProducts->pluck('id')->diff($matchedLocalIds->unique())->count(),
            'ambiguous' => $ambiguous,
            'invalid_remote_price' => $invalidRemotePrice,
            'matched_local_ids' => $matchedLocalIds->unique()->values()->all(),
            'matched_remote_ids' => $matchedRemoteIds->unique()->values()->all(),
            'supplier_offers' => $termOffers,
            'supplier_links' => $termLinks,
        ];
    }

    /**
     * Shop items linked by hand to Fireway cards: kept on the Камин-профессионал offer of the card (raw.deal_by_id),
     * new pairs of --pair saved with --apply.
     *
     * @param  Collection<int, Product>  $localProducts
     * @return array<int, int>
     */
    private function dealPairs(Collection $localProducts, Collection $remoteProducts): array
    {
        if (! in_array($this->supplierCode, [...self::MODEL_SUPPLIERS, 'termteplosistem'], true)) {
            return [];
        }
        $supplierId = (int) DB::table('suppliers')->where('code', $this->supplierCode)->value('id');
        $offers = SupplierProduct::query()->where('supplier_id', $supplierId)->whereNotNull('product_id')->get();
        $pairs = [];
        foreach ($offers as $offer) {
            if ((int) ($offer->raw['deal_by_id'] ?? 0) > 0) {
                $pairs[(int) $offer->raw['deal_by_id']] = (int) $offer->product_id;
            }
        }
        foreach ((array) $this->option('pair') as $pair) {
            [$dealId, $slug] = array_pad(explode(':', (string) $pair, 2), 2, '');
            // The workflow log may mask digits of a deal.by id: the slug of the shop item name works too.
            if (! ctype_digit(trim($dealId))) {
                $named = $remoteProducts->filter(fn (array $remote): bool => Str::slug((string) ($remote['name'] ?? '')) === trim($dealId));
                $dealId = $named->count() === 1 ? (string) ($named->first()['id'] ?? '') : '';
            }
            $product = $localProducts->firstWhere('slug', trim($slug));
            $offer = $product ? $offers->firstWhere('product_id', $product->id) : null;
            if ($this->supplierCode === 'termteplosistem') {
                $remote = $remoteProducts->firstWhere('id', (int) $dealId);
                if (! $remote || ! $product || (filled($remote['external_id'] ?? null) && $remote['external_id'] !== 'kotlov-'.$product->id)
                    || (isset($pairs[(int) $dealId]) && $pairs[(int) $dealId] !== (int) $product->id)
                    || (in_array((int) $product->id, $pairs, true) && ($pairs[(int) $dealId] ?? null) !== (int) $product->id)) {
                    throw new RuntimeException("Проверьте ID и существующие связи для пары {$pair}");
                }
            }
            if (! ctype_digit(trim($dealId)) || ! $offer) {
                $this->warn("--pair {$pair}: карточка поставщика не найдена.");

                continue;
            }
            $pairs[(int) $dealId] = (int) $product->id;
            if ($this->option('apply')) {
                $offer->forceFill(['raw' => array_merge((array) $offer->raw, ['deal_by_id' => (int) $dealId])])->save();
            }
        }

        return $pairs;
    }

    /**
     * The deal.by (Prom) import file of the cards the shop lacks. «Идентификатор_товара» kotlov-<id> links the new
     * shop item to its card by itself (external_id) in the next price sync. Cards without a photo are left out.
     *
     * @param  Collection<int, Product>  $products
     */
    private function exportMissing(Collection $products, string $name): void
    {
        $name = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'deal-export';
        $products = Product::query()->with(['category:id,name', 'brand:id,name'])->whereIn('id', $products->pluck('id'))
            ->get()->filter(fn (Product $product): bool => $product->imagePaths() !== [])->values();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Export Products Sheet');
        $sheet->fromArray([['Код_товара', 'Название_позиции', 'Поисковые_запросы', 'Описание', 'Тип_товара', 'Цена', 'Валюта', 'Единица_измерения',
            'Ссылка_изображения', 'Наличие', 'Название_группы', 'Идентификатор_товара', 'Производитель', 'Страна_производитель']]);
        $row = 2;
        foreach ($products as $product) {
            // The address the card page shows: an old card keeps a bare file name served through /proxy-image/.
            $images = collect(array_keys($product->imagePaths()))->take(5)
                ->map(fn (int $index) => ($src = $product->imageUrl($index)) && preg_match('~^https?://~i', $src) ? $src : url('/'.ltrim($src, '/')))->implode(', ');
            $sheet->fromArray([[
                $product->sku ?: 'KOTLOV-'.$product->id,
                $product->name,
                implode(', ', array_filter([$product->brand?->name, $product->category?->name])),
                (string) ($product->content ?: $product->short_description),
                'r',
                (float) $product->price,
                'BYN',
                $product->unit ?: 'шт.',
                $images,
                '+',
                (string) $product->category?->name,
                'kotlov-'.$product->id,
                (string) $product->brand?->name,
                'Россия',
            ]], null, 'A'.$row++);
        }
        $dir = public_path('exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        (new Xlsx($book))->save($dir.'/'.$name.'.xlsx');
        $this->line('metric.deal_brand_prices.exported='.$products->count());
        $this->line('audit.deal_brand_prices.export=exports/'.$name.'.xlsx');
    }

    /**
     * @param  Collection<int, Product>  $localProducts
     * @param  Collection<int, array<string, mixed>>  $remoteProducts
     * @param  array{updates: Collection<int, array<string, mixed>>, matched: int, unchanged: int, unmatched: int, ambiguous: int, invalid_remote_price: int}  $plan
     */
    private function metrics(Collection $localProducts, Collection $remoteProducts, array $plan): void
    {
        $this->line('metric.deal_brand_prices.remote_products='.$remoteProducts->count());
        if ($this->supplierCode !== null) {
            $this->line('metric.deal_brand_prices.local_supplier_'.$this->supplierCode.'='.$localProducts->count());
        }
        $this->line('metric.deal_brand_prices.local_vezuviy='.$localProducts->filter(
            fn (Product $product): bool => $this->normalize((string) $product->brand?->name) === 'везувий'
        )->count());
        $this->line('metric.deal_brand_prices.local_tatra_boilers='.$localProducts->filter(
            fn (Product $product): bool => in_array($this->normalize((string) $product->brand?->name), ['tatra', 'татра'], true)
        )->count());
        $this->line('metric.deal_brand_prices.matched='.$plan['matched']);
        $this->line('metric.deal_brand_prices.unchanged='.$plan['unchanged']);
        $this->line('metric.deal_brand_prices.price_mismatches='.$plan['updates']->count());
        $this->line('metric.deal_brand_prices.unmatched='.$plan['unmatched']);
        $this->line('metric.deal_brand_prices.ambiguous='.$plan['ambiguous']);
        $this->line('metric.deal_brand_prices.invalid_remote_price='.$plan['invalid_remote_price']);

        // ProMetall and Fireway goods on the shop as they are named there: to link the rest by hand.
        if (in_array($this->supplierCode, ['prometall', ...self::MODEL_SUPPLIERS], true)) {
            $pattern = match ($this->supplierCode) {
                'prometall' => '/атмосфер|прометалл|prometall|эйфори|бахт|арктик/ui',
                'akvateploservis' => self::GALMET_NAME,
                'metabel' => self::META_NAME,
                default => self::FIREWAY_NAME,
            };
            $matchedRemote = array_flip($plan['matched_remote_ids']);
            foreach ($remoteProducts as $remote) {
                // Fireway: only the shop items left without a card.
                if ($this->supplierCode !== 'prometall' && isset($matchedRemote[(int) ($remote['id'] ?? 0)])) {
                    continue;
                }
                if (preg_match($pattern, (string) ($remote['name'] ?? ''))) {
                    $this->line(sprintf('audit.deal_brand_prices.remote=%d:%s:%s:%d:%s', (int) ($remote['id'] ?? 0),
                        Str::slug((string) ($remote['sku'] ?? '')) ?: 'none', Str::slug((string) ($remote['external_id'] ?? '')) ?: 'none',
                        (int) round((float) ($remote['price'] ?? 0) * 100), Str::limit(Str::slug((string) ($remote['name'] ?? '')), 80, '')));
                }
            }
        }
        foreach ($plan['updates'] as $item) {
            $this->line(sprintf(
                'audit.deal_brand_prices=%d:%d:%s:%d:%d',
                $item['deal_id'],
                $item['local_id'],
                $item['group'],
                (int) round($item['old_price'] * 100),
                (int) round($item['new_price'] * 100),
            ));
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function loadRemoteProducts(DealByClient $client): Collection
    {
        $products = collect();
        $lastId = null;

        for ($page = 1; $page <= 2000; $page++) {
            $payload = $client->listProducts(self::PAGE_SIZE, $lastId);
            $items = collect($payload['products'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values();
            $products->push(...$items);

            if ($items->count() < self::PAGE_SIZE) {
                return $products;
            }

            $nextLastId = (int) data_get($items->last(), 'id');
            if ($nextLastId <= 0 || $nextLastId === $lastId) {
                throw new RuntimeException('Deal.by вернул некорректный курсор пагинации товаров.');
            }
            $lastId = $nextLastId;
        }

        throw new RuntimeException('Превышен безопасный предел в 200 000 товаров Deal.by.');
    }

    /** @return list<string> the model keys of a name for the supplier: Galmet ones, or Fireway exact and loose */
    private function modelKeys(string $name): array
    {
        if ($this->supplierCode === 'akvateploservis') {
            return array_values(array_filter([AkvateploservisPriceList::modelKey($name)]));
        }
        if ($this->supplierCode === 'metabel') {
            return self::metaKeys($name);
        }

        return self::firewayKeys($name);
    }

    /**
     * Мета-Бел model keys: the model left of a name once the brand, the kind of goods, the power and the
     * АОТ/ТКТ code are dropped («Печь-камин Мета-Бел Ока 6 кВт» = «Печь-камин АОТ 6.0 (в модификации "Ока")» =
     * «ока»), then the door/box/grill code alone («Печная дверца со стеклом Мета-Бел Сож ДК-01» = «code:дк01»).
     *
     * @return list<string>
     */
    public static function metaKeys(string $name): array
    {
        $name = str_replace('ё', 'е', mb_strtolower($name));
        $name = preg_replace('/\(\s*в\s+модификации\s+([^)]*)\)/u', ' $1 ', $name) ?? $name;
        $name = preg_replace('/(?<![а-яa-z])в\s+модификации(?![а-яa-z])/u', ' ', $name) ?? $name;
        $code = preg_match('/(?<![а-яa-z])(дп|дк|зя|ог|кн)\s*-?\s*(\d{1,3})(?!\d)/u', $name, $m) ? $m[1].$m[2] : null;
        $patterns = [
            '/мета[\s-]*бел/u', '/снято с производства/u', '/\([^)]*\)/u',
            '/(?<![а-яa-z])(аот|ткт)[кв]?\s*-?\s*\d+(?:[.,]\d+)?(?:\s*-\s*\d+)?/u', '/\d+\s*квт(?![а-я])/u', '/(?<![а-я])квт(?![а-я])/u',
            '/(?<![а-я])(конвекционная|отопительная|банная|каминная|каминный|печная|угловая|туннельная)(?![а-я])/u',
            '/печь-каменка|печь-камин|печь|каменка|топка|дверца|дверь|со стеклом|с водяным контуром|без пьедестала/u',
        ];
        $model = preg_replace($patterns, ' ', $name) ?? $name;
        $model = preg_replace('/[^a-zа-я0-9+]+/u', ' ', $model) ?? $model;
        $model = trim(preg_replace('/\s+/u', ' ', $model) ?? $model);

        return array_values(array_unique(array_filter([$model !== '' ? $model : null, $code ? 'code:'.$code : null])));
    }

    /** @return list<string> the exact model key first, then the looser one teplodvor-style names need */
    private static function firewayKeys(string $name): array
    {
        return array_values(array_unique(array_filter([
            KaminproffPriceList::modelKey($name),
            FirewayTeplodvorCatalog::looseKey($name),
        ])));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function normalizeSku(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function normalizeName(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value));
    }

    private function isTatra(Product $product): bool
    {
        return in_array($this->normalize((string) $product->brand?->name), ['tatra', 'татра'], true);
    }

    private function looksLikeTatra(mixed $value): bool
    {
        return preg_match('/(?:tatra|татра)/ui', (string) $value) === 1;
    }

    private function tatraModelKey(mixed ...$values): ?string
    {
        $original = mb_strtoupper(implode(' ', array_map(static fn (mixed $item): string => (string) $item, $values)));
        $value = $original;
        $value = strtr($value, [
            'А' => 'A', 'Б' => 'B', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
            'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'Т' => 'T',
        ]);
        $compact = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';

        if (preg_match('/KT(\d{2,3})(N(?:L|P)[12]?|N|TR)?/', $compact, $match) === 1) {
            $suffix = $match[2] ?? '';
            if (in_array($suffix, ['N', 'NL'], true) && preg_match('/(?:ЛЕВ|LEFT)/u', $original) === 1) {
                $suffix = 'NL1';
            } elseif (in_array($suffix, ['N', 'NP'], true) && preg_match('/(?:ПРАВ|RIGHT)/u', $original) === 1) {
                $suffix = 'NP1';
            }

            return 'KT'.$match[1].$suffix;
        }

        if (preg_match('/K(\d{2,3})TR/', $compact, $match) === 1) {
            return 'K'.$match[1].'TR';
        }

        if (preg_match('/TATRA.*?T(10|16|24|30|32|36)(T)?/', $compact, $match) === 1) {
            $model = 'T'.$match[1].($match[2] ?? '');
            if (preg_match('/(?:BAKOM?|TANK)(50|80|100)L?/', $compact, $tank) === 1) {
                $model .= 'TANK'.$tank[1];
            }

            return $model;
        }

        return null;
    }

    private function resolveSupplierCode(): ?string
    {
        $supplierCode = $this->normalize((string) $this->option('supplier'));
        if ($supplierCode === '') {
            return null;
        }

        if (! in_array($supplierCode, self::SUPPORTED_SUPPLIERS, true)) {
            throw new RuntimeException('Безопасная остановка: этот код поставщика не разрешён для сверки Deal.by.');
        }

        return $supplierCode;
    }

    /** @param Collection<int, array<string, mixed>> $remoteProducts */
    private function inspectRemoteProduct(Collection $remoteProducts, int $dealId): int
    {
        if ($dealId <= 0) {
            throw new RuntimeException('--inspect-id должен быть положительным ID товара Deal.by.');
        }

        $product = $remoteProducts->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $dealId);
        $this->line('metric.deal_brand_prices.inspect_found='.($product === null ? '0' : '1'));

        if ($product === null) {
            return self::FAILURE;
        }

        foreach ($this->priceFields($product) as $path => $value) {
            $this->line(sprintf(
                'audit.deal_brand_prices.field=%d:%s:%d',
                $dealId,
                $path,
                (int) round($value * 100),
            ));
        }

        return self::SUCCESS;
    }

    /** @return array<string, float> */
    private function priceFields(array $payload, string $prefix = ''): array
    {
        $fields = [];

        foreach ($payload as $key => $value) {
            $segment = preg_replace('/[^a-z0-9_-]+/i', '_', (string) $key) ?: 'field';
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                $fields += $this->priceFields($value, $path);

                continue;
            }

            if (preg_match('/(?:price|discount|cost|sale)/i', (string) $key) === 1 && is_numeric($value)) {
                $fields[$path] = (float) $value;
            }
        }

        return $fields;
    }
}
