<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The whole 1C «Номенклатура» on the site: groups and items read every morning (pages of the catalog), with the site
 * card each item is linked to, the group path, items the site created and items with the same name in one group.
 * Read-only: in 1C only new items are made, by OneCItemCreator.
 */
class OneCNomenclature
{
    public const ENTITY = 'Catalog_Номенклатура';

    /** Items per request (an answer stays far below 8 MB) and requests per read (up to 50 000 items). */
    public const PAGE = 2500;

    public const PAGES = 20;

    private const FIELDS = 'Ref_Key,Code,Description,Артикул,Parent_Key,IsFolder,DeletionMark,ВидНоменклатуры_Key,ЕдиницаИзмерения_Key';

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    public function enqueue(?User $user): OneCAgentTask
    {
        $queries = [];
        for ($i = 0; $i < self::PAGES; $i++) {
            $queries['items_'.($i + 1)] = ODataReadTaskHandler::path(self::ENTITY, ['$select' => self::FIELDS, '$orderby' => 'Ref_Key',
                '$top' => (string) self::PAGE, '$skip' => (string) ($i * self::PAGE)]);
        }

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, null, 'Номенклатура 1С', $user, ['queries' => $queries, 'nomenclature' => true]);
    }

    public function import(OneCAgentTask $task): string
    {
        $now = now();
        $seen = 0;
        $complete = true;
        $lastFull = true;
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            $part = ODataReadTaskHandler::part($task, $name);
            if ($part['code'] !== 200) {
                $complete = false;

                continue;
            }
            $rows = [];
            foreach ((array) data_get($part['json'], 'value', []) as $item) {
                $key = strtolower((string) ($item['Ref_Key'] ?? ''));
                if (! preg_match('/^[0-9a-f-]{36}$/', $key)) {
                    continue;
                }
                $parent = strtolower((string) ($item['Parent_Key'] ?? ''));
                $rows[] = ['ref_key' => $key, 'code' => mb_substr(trim((string) ($item['Code'] ?? '')), 0, 50) ?: null,
                    'description' => mb_substr(trim((string) ($item['Description'] ?? '')), 0, 500),
                    'article' => mb_substr(trim((string) ($item['Артикул'] ?? '')), 0, 100) ?: null,
                    'parent_key' => $parent !== '' && $parent !== OneCReferences::ZERO ? $parent : null,
                    'is_folder' => (bool) ($item['IsFolder'] ?? false), 'deletion_mark' => (bool) ($item['DeletionMark'] ?? false),
                    'kind_key' => self::key($item['ВидНоменклатуры_Key'] ?? null), 'unit_key' => self::key($item['ЕдиницаИзмерения_Key'] ?? null),
                    'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            }
            $lastFull = count($rows) >= self::PAGE;
            foreach (array_chunk($rows, 500) as $chunk) {
                OneCItem::query()->upsert($chunk, ['ref_key'], ['code', 'description', 'article', 'parent_key', 'is_folder', 'deletion_mark',
                    'kind_key', 'unit_key', 'synced_at', 'updated_at']);
            }
            $seen += count($rows);
        }
        // Items gone from 1C leave only when every page came and the last one was not full (nothing is beyond it).
        $removed = 0;
        if ($complete && ! $lastFull && $seen > 0) {
            $removed = OneCItem::query()->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', $now))
                ->where('created_at', '<', $task->created_at)->delete();
        }
        $this->refresh();

        return 'Номенклатура 1С: '.$seen.' (товары и группы)'.($removed ? ', удалено из 1С: '.$removed : '')
            .($complete ? '' : '. Не все страницы прочитаны — 1С не отдала часть ответа')
            .($lastFull ? '. Номенклатуры больше '.(self::PAGE * self::PAGES).': увеличьте число страниц' : '');
    }

    private static function key(mixed $value): ?string
    {
        $key = strtolower((string) $value);

        return preg_match('/^[0-9a-f-]{36}$/', $key) && $key !== OneCReferences::ZERO ? $key : null;
    }

    /** Group paths, the linked cards, items the site created and same names in one group. */
    public function refresh(): void
    {
        $folders = OneCItem::query()->where('is_folder', true)->pluck('parent_key', 'ref_key')->all();
        $names = OneCItem::query()->where('is_folder', true)->pluck('description', 'ref_key')->all();
        $path = function (?string $key) use ($folders, $names): ?string {
            $parts = [];
            for ($depth = 0; $key && isset($names[$key]) && $depth < 12; $depth++) {
                array_unshift($parts, $names[$key]);
                $key = $folders[$key] ?? null;
            }

            return $parts ? mb_substr(implode(' / ', $parts), 0, 1000) : null;
        };

        $source = IntegrationSource::query()->where('code', 'onec')->value('id');
        $links = [];
        $fromSite = [];
        if ($source) {
            $rows = IntegrationProduct::query()->where('integration_source_id', $source)
                ->get(['external_id', 'product_id', 'match_method', 'match_status']);
            foreach ($rows->filter(fn ($row) => OneCItemLinks::identity($row->external_id))
                ->groupBy(fn ($row) => explode('#', strtolower($row->external_id))[0]) as $key => $group) {
                $links[$key] = OneCItemLinks::summary($group);
                $fromSite[$key] = $group->contains(fn ($row) => $row->match_method === 'onec_bridge');
            }
        }

        $same = [];
        OneCItem::query()->where('is_folder', false)->where('deletion_mark', false)->select(['parent_key', 'description'])
            ->chunk(5000, function ($rows) use (&$same): void {
                foreach ($rows as $row) {
                    $k = $row->parent_key.'|'.self::norm($row->description);
                    $same[$k] = ($same[$k] ?? 0) + 1;
                }
            });

        OneCItem::query()->select(['id', 'ref_key', 'parent_key', 'description', 'is_folder', 'deletion_mark', 'path', 'product_id', 'created_from_site', 'same_name_count', 'site_link_count', 'characteristic_count', 'link_conflict'])
            ->chunkById(1000, function ($items) use ($path, $links, $fromSite, $same): void {
                foreach ($items as $item) {
                    $values = [
                        'path' => $path($item->parent_key),
                        ...($links[$item->ref_key] ?? ['product_id' => null, 'site_link_count' => 0, 'characteristic_count' => 0, 'link_conflict' => false]),
                        'created_from_site' => $item->created_from_site || ($fromSite[$item->ref_key] ?? false),
                        'same_name_count' => $item->is_folder || $item->deletion_mark ? 0 : min(999, $same[$item->parent_key.'|'.self::norm($item->description)] ?? 0),
                    ];
                    if ($values['site_link_count'] !== $item->site_link_count || $values['characteristic_count'] !== $item->characteristic_count
                        || $values['link_conflict'] !== $item->link_conflict || $values['path'] !== $item->path || $values['product_id'] !== $item->product_id
                        || $values['created_from_site'] !== $item->created_from_site || $values['same_name_count'] !== $item->same_name_count) {
                        DB::table('onec_items')->where('id', $item->id)->update($values);
                    }
                }
            });
    }

    private static function norm(?string $name): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $name)));
    }

    /** A 1C item the site has just created or linked shows at once, before the next morning read. */
    public function remember(string $key, string $code, string $name, int $productId, ?string $parentKey, ?string $article, bool $created): void
    {
        $key = strtolower($key);
        $parentKey = self::key($parentKey);
        $item = OneCItem::query()->firstOrNew(['ref_key' => $key]);
        $item->fill(['code' => $code ?: $item->code, 'description' => $name ?: $item->description, 'article' => $article ?: $item->article,
            'parent_key' => $parentKey ?? $item->parent_key, 'product_id' => $productId, 'created_from_site' => $item->created_from_site || $created]);
        if ($item->parent_key) {
            $parent = OneCItem::query()->where('ref_key', $item->parent_key)->first(['description', 'path']);
            $item->path = $parent ? trim(($parent->path ? $parent->path.' / ' : '').$parent->description) : $item->path;
        }
        $item->save();
    }

    /** @return array<string, string> folders as an indented list (ref_key => «Котлы / Электрические»), for a select */
    public function folderOptions(): array
    {
        return OneCItem::query()->where('is_folder', true)->where('deletion_mark', false)->get(['ref_key', 'description', 'path'])
            ->mapWithKeys(fn (OneCItem $f): array => [$f->ref_key => trim(($f->path ? $f->path.' / ' : '').$f->description)])
            ->sort()->all();
    }

    /** @return list<string> a folder and all folders inside it */
    public function folderWithChildren(string $key): array
    {
        $children = OneCItem::query()->where('is_folder', true)->pluck('parent_key', 'ref_key')->all();
        $result = [$key];
        for ($i = 0; $i < count($result) && $i < 2000; $i++) {
            foreach ($children as $child => $parent) {
                if ($parent === $result[$i] && ! in_array($child, $result, true)) {
                    $result[] = $child;
                }
            }
        }

        return $result;
    }

    public function lastSync(): ?OneCAgentTask
    {
        return OneCAgentTask::query()->where('type', ODataReadTaskHandler::TYPE)->where('status', OneCAgentTask::DONE)
            ->where('payload->nomenclature', true)->latest('id')->first();
    }
}
