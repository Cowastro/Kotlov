<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Models\OneCAgentTask;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only: the agent GETs a few 1C lists through OData and uploads each answer as it is. Used to learn the
 * keys of organizations, warehouses, partners and agreements and the exact shape of «Заказ клиента».
 */
class ODataReadTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'odata_read';

    public const MAX_BYTES = 8 * 1024 * 1024;

    /** What the order export needs to know about this 1C base. name => [entity, query] */
    public const ORDER_REFERENCES = [
        'organizations' => ['Catalog_Организации', ['$select' => 'Ref_Key,Description,DeletionMark', '$top' => '50']],
        'warehouses' => ['Catalog_Склады', ['$select' => 'Ref_Key,Description,IsFolder,DeletionMark', '$top' => '300']],
        'retail_partner' => ['Catalog_Партнеры', ['$filter' => "substringof('Розничн',Description)", '$top' => '20']],
        'agreements' => ['Catalog_СоглашенияСКлиентами', ['$top' => '300']],
        'counterparty_sample' => ['Catalog_Контрагенты', ['$top' => '3']],
        'vat_rates' => ['Catalog_СтавкиНДС', ['$top' => '50']],
        'currencies' => ['Catalog_Валюты', ['$select' => 'Ref_Key,Description,Code']],
        'order_sample' => ['Document_ЗаказКлиента', ['$top' => '2', '$orderby' => 'Date desc']],
    ];

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    public function label(): string
    {
        return 'Чтение 1С';
    }

    public function enqueueOrderReferences(?User $user): OneCAgentTask
    {
        $queries = [];
        foreach (self::ORDER_REFERENCES as $name => [$entity, $query]) {
            $queries[$name] = self::path($entity, $query);
        }

        return $this->tasks->enqueue(self::TYPE, null, 'Справочники 1С для заказов', $user, ['queries' => $queries]);
    }

    /** @param array<string, string> $query */
    public static function path(string $entity, array $query): string
    {
        $parts = ['$format=json'];
        foreach ($query as $key => $value) {
            $parts[] = $key.'='.rawurlencode($value);
        }

        return rawurlencode($entity).'?'.implode('&', $parts);
    }

    public function lines(OneCAgentTask $task): array
    {
        $lines = [];
        foreach ((array) data_get($task->payload, 'queries', []) as $name => $path) {
            $lines[] = 'get|'.$name.'|'.$path;
        }

        return $lines;
    }

    /** One answer of 1C, stored as it came (only the names the task asked for). */
    public function storePart(OneCAgentTask $task, string $name, int $code, string $body): void
    {
        DB::transaction(function () use ($task, $name, $code, $body): void {
            $task = OneCAgentTask::query()->lockForUpdate()->findOrFail($task->id);
            if (! in_array($task->type, [self::TYPE, DocumentStatusTaskHandler::TYPE], true) || $task->status !== OneCAgentTask::SENDING) {
                throw new DomainException('Задача не ждёт данных агента.');
            }
            if (! array_key_exists($name, (array) data_get($task->payload, 'queries', []))) {
                throw new DomainException('Задача не просила «'.$name.'».');
            }
            if (strlen($body) > self::MAX_BYTES) {
                throw new DomainException('Ответ 1С больше 8 МБ.');
            }
            Storage::disk('local')->put(self::file($task, $name), $body);
            $payload = $task->payload ?? [];
            $payload['parts'][$name] = ['code' => $code, 'bytes' => strlen($body)];
            $task->forceFill(['payload' => $payload])->save();
        });
    }

    public static function file(OneCAgentTask $task, string $name): string
    {
        return 'onec-bridge/task-'.$task->id.'/'.$name.'.json';
    }

    /** @return array{code: ?int, bytes: int, json: mixed, raw: ?string} */
    public static function part(OneCAgentTask $task, string $name): array
    {
        $meta = data_get($task->payload, 'parts.'.$name);
        $raw = $meta && Storage::disk('local')->exists(self::file($task, $name)) ? Storage::disk('local')->get(self::file($task, $name)) : null;

        return ['code' => $meta['code'] ?? null, 'bytes' => (int) ($meta['bytes'] ?? 0),
            'json' => $raw !== null ? json_decode($raw, true) : null, 'raw' => $raw];
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        $task->refresh();
        $asked = count((array) data_get($task->payload, 'queries', []));
        $read = collect((array) data_get($task->payload, 'parts', []))->filter(fn (array $p): bool => $p['code'] === 200)->count();

        return $result['ok'] ? 'Прочитано '.$read.' из '.$asked : null;
    }

    public function requeued(OneCAgentTask $task): void
    {
        $payload = $task->payload ?? [];
        unset($payload['parts']);
        $task->forceFill(['payload' => $payload])->save();
    }

    public function cancelled(OneCAgentTask $task): void {}

    public function url(OneCAgentTask $task): ?string
    {
        return OneCAgentTaskResource::getUrl('view', ['record' => $task]);
    }
}
