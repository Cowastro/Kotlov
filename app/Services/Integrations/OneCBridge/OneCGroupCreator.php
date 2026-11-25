<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

/** Groups use the existing nomenclature agent protocol; no client agent update is needed. */
class OneCGroupCreator
{
    public static function name(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    public static function identity(string $name): string
    {
        return mb_strtolower(self::name($name));
    }

    public function request(string $name, ?string $parentKey, ?User $user): OneCAgentTask
    {
        if (! $user?->is_active || ! $user->isManager()) {
            throw new DomainException('Создавать группы может только действующий менеджер.');
        }
        if (! app(OneCItemCreator::class)->source()) {
            throw new DomainException('Нет подключения центральной 1С.');
        }
        $name = self::name($name);
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\p{Cc}\/\\\\]/u', $name)) {
            throw new DomainException('Введите название группы до 100 символов без слешей.');
        }
        $parentKey = strtolower(trim((string) $parentKey));
        $parentKey = $parentKey === '' || $parentKey === OneCReferences::ZERO ? null : $parentKey;
        $parent = $parentKey ? OneCItem::query()->where('ref_key', $parentKey)->where('is_folder', true)->where('deletion_mark', false)->first() : null;
        if ($parentKey && ! $parent) {
            throw new DomainException('Выберите существующую родительскую группу.');
        }
        // The key also makes a repeated POST safe if the agent lost the response to a successful create.
        $operation = Uuid::uuid5(Uuid::NAMESPACE_URL, 'kotlov:onec-group:'.($parentKey ?? OneCReferences::ZERO).':'.self::identity($name))->toString();

        return Cache::lock('onec-create-group', 20)->block(5, function () use ($name, $parentKey, $parent, $operation, $user): OneCAgentTask {
            $exists = OneCItem::query()->where('is_folder', true)->where('deletion_mark', false)
                ->where('parent_key', $parentKey)->get(['description'])
                ->contains(fn (OneCItem $item): bool => self::identity($item->description) === self::identity($name));
            if ($exists) {
                throw new DomainException('В этой папке группа «'.$name.'» уже существует. Выберите её в дереве.');
            }
            if (OneCAgentTask::query()->where('payload->group_operation', $operation)->whereIn('status', OneCAgentTask::ACTIVE)->exists()) {
                throw new DomainException('Эта группа уже создаётся. Дождитесь ответа 1С.');
            }
            $folderFilter = 'IsFolder eq true and DeletionMark eq false';
            $queries = [
                'siblings' => ODataReadTaskHandler::path(OneCItemCreator::ENTITY, [
                    '$filter' => $folderFilter." and Parent_Key eq guid'".($parentKey ?? OneCReferences::ZERO)."'",
                    '$select' => 'Ref_Key,Code,Description,Parent_Key,IsFolder,DeletionMark', '$top' => '2001',
                ]),
            ];
            if ($parentKey) {
                $queries['parent'] = ODataReadTaskHandler::path(OneCItemCreator::ENTITY, [
                    '$filter' => $folderFilter." and Ref_Key eq guid'".$parentKey."'", '$top' => '1',
                ]);
            }

            return app(OneCAgentTasks::class)->enqueue(ODataReadTaskHandler::TYPE, null, 'Проверить группу 1С: '.$operation, $user, [
                'group_operation' => $operation, 'queries' => $queries,
                'create_group' => ['name' => $name, 'parent' => $parentKey, 'path' => $parent ? trim(($parent->path ? $parent->path.' / ' : '').$parent->description) : null],
            ]);
        });
    }

    public function afterRead(OneCAgentTask $task): string
    {
        foreach (array_keys($task->payload['queries']) as $part) {
            $answer = ODataReadTaskHandler::part($task, $part);
            if ($answer['code'] !== 200 || ! is_array(data_get($answer, 'json.value'))) {
                throw new DomainException('Не удалось проверить группы в 1С. Группа не создана: повторите проверку.');
            }
        }
        $data = $task->payload['create_group'];
        if ($data['parent'] && ! data_get(ODataReadTaskHandler::part($task, 'parent'), 'json.value.0')) {
            throw new DomainException('Родительская группа больше не существует в 1С. Создание остановлено.');
        }
        $answer = ODataReadTaskHandler::part($task, 'siblings')['json'];
        $siblings = $answer['value'];
        if (count($siblings) > 2000 || isset($answer['odata.nextLink']) || isset($answer['@odata.nextLink'])) {
            throw new DomainException('1С вернула не все группы. Создание остановлено во избежание дубля.');
        }
        // Import this parent's current children, so a group made directly in 1C appears immediately too.
        foreach ($siblings as $row) {
            $this->remember($row, $data['path'], false);
        }
        $matches = collect($siblings)->filter(fn (array $row): bool => self::identity((string) ($row['Description'] ?? '')) === self::identity($data['name']));
        if ($matches->isNotEmpty()) {
            return 'Группа «'.$data['name'].'» уже есть в 1С и показана в дереве. Новая не создана.';
        }
        $body = ['Ref_Key' => $task->payload['group_operation'], 'IsFolder' => true, 'Description' => $data['name'],
            'Parent_Key' => $data['parent'] ?? OneCReferences::ZERO];
        $filter = "IsFolder eq true and DeletionMark eq false and Parent_Key eq guid'".$body['Parent_Key']."' and Description eq '".str_replace("'", "''", $data['name'])."'";
        app(OneCAgentTasks::class)->enqueue(ItemTaskHandler::TYPE, null, 'Создать группу 1С: '.$task->payload['group_operation'], $task->creator, [
            'group_operation' => $task->payload['group_operation'], 'create_group' => $data, 'body' => $body,
            'find' => ODataReadTaskHandler::path(OneCItemCreator::ENTITY, ['$filter' => $filter, '$select' => 'Ref_Key,Code,Description', '$top' => '1']),
        ]);

        return 'Группа «'.$data['name'].'» проверена, ожидает создания в 1С.';
    }

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        if (! $result['ok']) {
            return null;
        }
        $key = strtolower((string) ($result['data']['document_key'] ?? ''));
        if (! Uuid::isValid($key) || $key === OneCReferences::ZERO) {
            throw new DomainException('Агент не вернул ключ группы 1С.');
        }
        $data = $task->payload['create_group'];
        $existed = ($result['data']['existed'] ?? '') === '1';
        $this->remember(['Ref_Key' => $key, 'Code' => $result['data']['document_code'] ?? $result['data']['document_number'] ?? '',
            'Description' => $data['name'], 'Parent_Key' => $data['parent']], $data['path'], ! $existed);

        return 'Группа «'.$data['name'].'» '.($existed ? 'уже существовала в 1С' : 'создана в 1С').'. Дерево обновлено.';
    }

    private function remember(array $row, ?string $path, bool $created): void
    {
        $key = strtolower((string) ($row['Ref_Key'] ?? ''));
        if (! Uuid::isValid($key) || $key === OneCReferences::ZERO) {
            throw new DomainException('1С вернула группу без корректного ключа. Создание остановлено.');
        }
        $parent = strtolower((string) ($row['Parent_Key'] ?? ''));
        $item = OneCItem::query()->firstOrNew(['ref_key' => $key]);
        if ($item->exists && ! $item->is_folder) {
            throw new DomainException('Ключ группы занят товаром. Создание остановлено.');
        }
        $item->fill(['description' => $row['Description'], 'code' => $row['Code'] ?: null,
            'parent_key' => $parent && $parent !== OneCReferences::ZERO ? $parent : null, 'is_folder' => true,
            'deletion_mark' => false, 'path' => $path, 'synced_at' => now(), 'created_from_site' => $created || $item->created_from_site])->save();
    }
}
