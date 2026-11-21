<?php

namespace App\Http\Controllers;

use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Integrations\OneCBridge\PriceBatchTaskHandler;
use App\Services\Integrations\OneCPriceBatches;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «Мост 1С»: the agent on the 1C server asks for the next task, does it in 1C and reports the result.
 * PowerShell 2.0 there has no JSON parser, so a task comes as plain lines, one field per line, «|» separated.
 */
class OneCAgentController extends Controller
{
    public function __construct(
        private readonly OneCAgentTasks $tasks,
        private readonly OneCPriceBatches $batches,
    ) {}

    public function ping(Request $request): JsonResponse
    {
        return $this->authorized($request) ? response()->json(['ok' => true, 'time' => now()->toIso8601String()])
            : $this->denied();
    }

    /** The next task: «task|id|type», the lines of its kind, «end»; «none» when the queue is empty. */
    public function nextTask(Request $request): JsonResponse|Response
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        // An agent older than 4 cannot create orders: they wait for the updated agent.ps1.
        $types = (int) $request->header('X-Kotlov-Agent', '1') < 4
            ? array_values(array_diff(array_keys($this->tasks->types()), [OrderTaskHandler::TYPE])) : null;
        $task = $this->tasks->claimNext($types);
        if (! $task) {
            return $this->text("none\n");
        }
        $lines = $this->tasks->handler($task->type)->lines($task);

        return $this->text(implode("\n", ['task|'.$task->id.'|'.$task->type, ...$lines, 'end'])."\n");
    }

    public function taskResult(Request $request, int $task): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $record = OneCAgentTask::query()->find($task);
        if (! $record) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $data = collect($request->except(['ok', 'error']))->filter(fn ($v): bool => is_scalar($v))->all();

        return $this->finish($record, ['ok' => $request->input('ok'), 'error' => $request->input('error'), 'data' => $data]);
    }

    /** One answer of 1C for a read task, the body as it came. */
    public function taskPart(Request $request, int $task): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $record = OneCAgentTask::query()->find($task);
        if (! $record) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        try {
            app(ODataReadTaskHandler::class)->storePart($record, (string) $request->query('name'), (int) $request->query('code'), $request->getContent());
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    /** The first agent (version 1) knows prices only: it asks here and answers to priceBatchResult. */
    public function next(Request $request): JsonResponse|Response
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $task = $this->tasks->claimNext([PriceBatchTaskHandler::TYPE]);
        $payload = $task ? $this->batches->payload(OneCPriceBatch::query()->findOrFail($task->subject_id)) : null;

        if ($request->query('format') === 'lines') {
            return $this->text($payload ? implode("\n", PriceBatchTaskHandler::batchLines($payload))."\nend\n" : "none\n");
        }

        return response()->json(['batch' => $payload]);
    }

    public function result(Request $request, int $batch): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->denied();
        }
        $task = OneCAgentTask::query()->where('type', PriceBatchTaskHandler::TYPE)->where('subject_type', (new OneCPriceBatch)->getMorphClass())
            ->where('subject_id', $batch)->where('status', OneCAgentTask::SENDING)->latest('id')->first();
        if (! $task) {
            return response()->json(['ok' => false, 'error' => OneCPriceBatch::query()->whereKey($batch)->exists() ? 'Пакет не ждёт ответа агента.' : 'not_found'],
                OneCPriceBatch::query()->whereKey($batch)->exists() ? 409 : 404);
        }

        return $this->finish($task, ['ok' => $request->input('ok'), 'error' => $request->input('error'),
            'data' => array_filter($request->only(['document_number', 'document_key']), 'is_scalar')]);
    }

    /** @param array{ok: mixed, error: mixed, data: array<string, mixed>} $result */
    private function finish(OneCAgentTask $task, array $result): JsonResponse
    {
        try {
            $task = $this->tasks->finish($task, $result);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }
        $status = $task->type === PriceBatchTaskHandler::TYPE ? OneCPriceBatch::query()->find($task->subject_id)?->status : $task->status;

        return response()->json(['ok' => true, 'status' => $status, 'task_status' => $task->status]);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function authorized(Request $request): bool
    {
        $token = OneCAgentToken::findActive($request->bearerToken());
        if ($token) {
            $version = mb_substr(preg_replace('/[^0-9.]/', '', (string) $request->header('X-Kotlov-Agent', '')), 0, 20);
            $token->forceFill(['last_seen_at' => now(), 'agent_version' => $version !== '' ? $version : '1'])->saveQuietly();
        }

        return $token !== null;
    }

    private function denied(): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }
}
