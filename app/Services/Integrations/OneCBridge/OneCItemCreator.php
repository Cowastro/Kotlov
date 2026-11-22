<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

/**
 * A site card that 1C does not have becomes a 1C item («Номенклатура») made after a similar 1C item: its kind,
 * unit, VAT, group and accounting settings are copied, the name and article come from the card. Duplicates are
 * stopped twice: the manager sees similar 1C items first, and before creating the agent looks in 1C for an item with
 * the same article or name; a found one is linked instead.
 *
 * Two tasks: «read» (the sample item and the look-ups, ODataReadTaskHandler) and «create» (ItemTaskHandler); the
 * second is queued by the site as soon as the first is answered, the agent does both in one run.
 */
class OneCItemCreator
{
    public const ENTITY = 'Catalog_Номенклатура';

    /** 1C limits: «Наименование» 100, «Артикул» 25 characters. */
    private const NAME_MAX = 100;

    private const ARTICLE_MAX = 25;

    /** Fields of the sample that belong to the sample itself. */
    private const OWN_FIELDS = ['Ref_Key', 'DataVersion', 'Code', 'Description', 'НаименованиеПолное', 'Артикул', 'DeletionMark',
        'Predefined', 'PredefinedDataName', 'IsFolder', 'Описание', 'Комментарий', 'ТекстИсправления'];

    /** References of the sample that describe that very product (its picture, maker, brand): left empty. */
    private const OWN_REFERENCES = ['ФайлКартинки_Key', 'Производитель_Key', 'Марка_Key', 'Изготовитель_Key', 'ОсновнойПоставщик_Key'];

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    public function source(): ?IntegrationSource
    {
        return IntegrationSource::query()->where('code', 'onec')->first();
    }

    /** The 1C item a card is linked with, if any. */
    public function linked(Product $product): ?IntegrationProduct
    {
        $source = $this->source();

        return $source ? IntegrationProduct::query()->where('integration_source_id', $source->id)->where('product_id', $product->id)
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))->first() : null;
    }

    /** @return array<int, string> 1C items that may be this card: by article and by the words of the name */
    public function similar(Product $product, int $limit = 8): array
    {
        $source = $this->source();
        if (! $source) {
            return [];
        }
        // The first words say what the thing is («Датчик загазованности…»); the database compares without case.
        $words = collect(preg_split('/[\s,.;:()«»"\/+]+/u', (string) $product->name))
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3)->take(2)->values();
        $rows = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where(function ($q) use ($product, $words): void {
                if (filled($product->sku)) {
                    $q->orWhere('external_sku', $product->sku);
                }
                if ($words->count() >= 2) {
                    $q->orWhere(function ($q) use ($words): void {
                        foreach ($words as $word) {
                            $q->where('name', 'like', '%'.$word.'%');
                        }
                    });
                }
            })->limit($limit)->get(['id', 'external_code', 'name', 'product_id', 'stock_quantity']);

        return $rows->mapWithKeys(fn (IntegrationProduct $p): array => [$p->id => trim(($p->external_code ? $p->external_code.' · ' : '').$p->name)
            .($p->product_id ? ' (уже у карточки #'.$p->product_id.')' : '')])->all();
    }

    /** The 1C item to copy settings from: the latest linked item of the same site category, otherwise any linked one. */
    public function defaultTemplate(Product $product): ?string
    {
        $source = $this->source();
        if (! $source) {
            return null;
        }
        if ($best = $this->closestByName($product, $source->id)) {
            return self::itemKey($best);
        }
        $linked = fn () => IntegrationProduct::query()->where('integration_source_id', $source->id)->whereNotNull('product_id')
            ->where('match_status', 'matched')->latest('matched_at');
        $row = $linked()->whereHas('product', fn ($q) => $q->where('category_id', $product->category_id))->first() ?? $linked()->first();

        return $row ? self::itemKey($row) : null;
    }

    /**
     * The 1C item whose name shares most words with the card (brand, series, kind: «Ballu», «BEC», «конвектор»), so a
     * convector is made after a convector, not after the latest boiler of a mixed site category. Ties: a short plain
     * name («Конвектор … BEC/HMM-1500») before a part of a set («Модуль … для конвектора …»), then one of the same category.
     */
    private function closestByName(Product $product, int $sourceId): ?IntegrationProduct
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $product->name)))
            // Bare numbers (power, size) say little about the kind of item: «2000» must not pull in a module for a 2000 W set.
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3 && ! ctype_digit($w))->unique()->values();
        if ($words->isEmpty()) {
            return null;
        }
        $rows = IntegrationProduct::query()->where('integration_source_id', $sourceId)
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->where(function ($q) use ($words): void {
                foreach ($words->take(4) as $word) {
                    $q->orWhere('name', 'like', '%'.$word.'%');
                }
            })->limit(300)->with('product:id,category_id')->get(['id', 'external_id', 'name', 'product_id']);
        $best = $rows->map(function (IntegrationProduct $row) use ($words, $product): array {
            $name = mb_strtolower((string) $row->name);
            $score = $words->filter(fn (string $w): bool => str_contains($name, $w))->count();

            return ['row' => $row, 'score' => $score, 'same' => $row->product?->category_id === $product->category_id, 'len' => mb_strlen($name)];
        })->filter(fn (array $c): bool => $c['score'] >= 2 && self::itemKey($c['row']) !== null)
            ->sort(fn (array $a, array $b): int => [$b['score'], $b['same'], $a['len']] <=> [$a['score'], $a['same'], $b['len']])
            ->first();

        return $best['row'] ?? null;
    }

    /** @return array<string, string> 1C items (key => label) to choose a sample from */
    public function searchTemplates(string $text): array
    {
        $source = $this->source();
        if (! $source || mb_strlen(trim($text)) < 2) {
            return [];
        }

        return IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where(fn ($q) => $q->where('name', 'like', '%'.trim($text).'%')->orWhere('external_code', 'like', trim($text).'%'))
            ->limit(30)->get(['external_id', 'external_code', 'name'])
            ->mapWithKeys(fn (IntegrationProduct $p): array => [self::itemKey($p) => trim(($p->external_code ? $p->external_code.' · ' : '').$p->name)])
            ->filter(fn ($label, $key): bool => (bool) $key)->all();
    }

    public function templateLabel(?string $key): ?string
    {
        $source = $this->source();
        $row = $source && $key ? IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where(fn ($q) => $q->where('external_id', $key)->orWhere('external_id', 'like', $key.'#%'))->first() : null;

        return $row ? trim(($row->external_code ? $row->external_code.' · ' : '').$row->name) : $key;
    }

    public static function itemKey(IntegrationProduct $row): ?string
    {
        $key = strtolower(explode('#', (string) $row->external_id)[0]);

        return preg_match('/^[0-9a-f-]{36}$/', $key) ? $key : null;
    }

    public static function article(Product $product): string
    {
        return mb_substr(trim((string) $product->sku), 0, self::ARTICLE_MAX);
    }

    /** Step 1: read the sample and look in 1C for the same article or name. */
    public function request(Product $product, string $templateKey, ?string $name, ?User $user): OneCAgentTask
    {
        if (! $this->source()) {
            throw new DomainException('Нет подключения центральной 1С.');
        }
        if ($existing = $this->linked($product)) {
            throw new DomainException('Карточка уже связана с товаром 1С '.($existing->external_code ?: $existing->name).'.');
        }
        foreach ([ODataReadTaskHandler::TYPE, ItemTaskHandler::TYPE] as $type) {
            if ($this->tasks->activeFor($product, $type)) {
                throw new DomainException('Товар уже создаётся в 1С — дождитесь агента (5 минут).');
            }
        }
        $templateKey = strtolower(trim($templateKey));
        if (! preg_match('/^[0-9a-f-]{36}$/', $templateKey)) {
            throw new DomainException('Выберите товар 1С — образец.');
        }
        $name = trim((string) ($name ?: $product->name));
        $short = mb_substr($name, 0, self::NAME_MAX);
        $article = self::article($product);
        $quote = fn (string $v): string => str_replace("'", "''", $v);
        $queries = [
            'template' => ODataReadTaskHandler::path(self::ENTITY, ['$filter' => "Ref_Key eq guid'".$templateKey."'"]),
            'by_name' => ODataReadTaskHandler::path(self::ENTITY, ['$filter' => "Description eq '".$quote($short)."' and DeletionMark eq false",
                '$select' => 'Ref_Key,Code,Description', '$top' => '3']),
        ];
        if ($article !== '') {
            $queries['by_article'] = ODataReadTaskHandler::path(self::ENTITY, ['$filter' => "Артикул eq '".$quote($article)."' and DeletionMark eq false",
                '$select' => 'Ref_Key,Code,Description', '$top' => '3']);
        }

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, $product, 'Товар в 1С: '.Str::limit($short, 120), $user, [
            'queries' => $queries,
            'create_item' => ['product_id' => $product->id, 'name' => $short, 'full_name' => trim((string) $product->name) ?: $name, 'article' => $article,
                'template' => $templateKey, 'user_id' => $user?->id],
        ]);
    }

    /** Step 2, when 1C answered the read: link an item 1C already has, or queue its creation. */
    public function afterRead(OneCAgentTask $task): string
    {
        $data = (array) data_get($task->payload, 'create_item');
        $product = Product::query()->find($data['product_id'] ?? null);
        if (! $product) {
            return 'Карточка удалена — товар не создан';
        }
        foreach (['by_article', 'by_name'] as $name) {
            $found = data_get(ODataReadTaskHandler::part($task, $name), 'json.value.0');
            if (is_array($found) && preg_match('/^[0-9a-f-]{36}$/i', (string) ($found['Ref_Key'] ?? ''))) {
                return 'В 1С уже есть '.($found['Code'] ?? '').' «'.($found['Description'] ?? '').'»: '
                    .$this->link($product, (string) $found['Ref_Key'], (string) ($found['Code'] ?? ''), (string) ($found['Description'] ?? ''), false);
            }
        }
        $template = data_get(ODataReadTaskHandler::part($task, 'template'), 'json.value.0');
        if (! is_array($template)) {
            return 'Образец не найден в 1С — товар не создан, выберите другой образец';
        }
        $user = User::query()->find($data['user_id'] ?? null);
        $this->tasks->enqueue(ItemTaskHandler::TYPE, $product, 'Создать в 1С: '.Str::limit((string) $data['name'], 120), $user, [
            'body' => $this->body($template, $data),
            'find' => $data['article'] !== '' ? data_get($task->payload, 'queries.by_article') : data_get($task->payload, 'queries.by_name'),
            'template_code' => $template['Code'] ?? null,
        ]);

        return 'Образец '.($template['Code'] ?? '').' прочитан, похожего товара в 1С нет — создаю';
    }

    /** @return array<string, mixed> the new item: the sample's settings, the card's name and article */
    public function body(array $template, array $data): array
    {
        $body = [];
        foreach ($template as $key => $value) {
            $key = (string) $key;
            if (is_array($value) || str_contains($key, '@') || in_array($key, self::OWN_FIELDS, true)) {
                continue;
            }
            if (in_array($key, self::OWN_REFERENCES, true)) {
                $body[$key] = OneCReferences::ZERO;

                continue;
            }
            // Text of the sample (its description, notes) stays out; one-word values are 1C enums (Товар, НеИспользовать…).
            if (is_string($value) && ! str_ends_with($key, '_Key') && ! str_ends_with($key, '_Type') && $value !== ''
                && ! preg_match('/^[А-ЯЁA-Z][А-ЯЁа-яёA-Za-z0-9]{0,60}$/u', $value) && ! preg_match('/^\d{4}-\d\d-\d\dT/', $value)) {
                $body[$key] = '';

                continue;
            }
            $body[$key] = $value;
        }
        $body['Description'] = (string) $data['name'];
        $body['НаименованиеПолное'] = (string) $data['full_name'];
        $body['Артикул'] = (string) $data['article'];

        return $body;
    }

    /** The card gets the 1C item (the row the 1C exchange keeps for it later). A 1C item of another card is not taken. */
    public function link(Product $product, string $key, string $code, string $name, bool $created): string
    {
        $source = $this->source();
        $key = strtolower($key);
        $row = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where(fn ($q) => $q->where('external_id', $key)->orWhere('external_id', 'like', $key.'#%'))->first();
        if ($row && $row->product_id && (int) $row->product_id !== (int) $product->id) {
            return 'он привязан к другой карточке #'.$row->product_id.' — карточку не привязал, проверьте дубли';
        }
        $row ??= new IntegrationProduct(['integration_source_id' => $source->id, 'external_id' => $key, 'price' => 0]);
        $row->forceFill(['external_code' => $code ?: $row->external_code, 'name' => $name ?: $row->name, 'external_sku' => $row->external_sku ?: (self::article($product) ?: null),
            'product_id' => $product->id, 'match_status' => 'matched', 'match_method' => 'onec_bridge', 'match_confidence' => 1, 'matched_at' => now()])->save();

        return ($created ? 'создан и привязан' : 'карточка привязана к нему').' (карточка #'.$product->id.')';
    }
}
