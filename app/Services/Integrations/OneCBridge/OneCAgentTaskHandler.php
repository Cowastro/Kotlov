<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;

/** One kind of work the agent on the 1C server can do (prices, orders, ...). */
interface OneCAgentTaskHandler
{
    /** Short name shown in «Мост 1С». */
    public function label(): string;

    /**
     * What the agent needs, one field per line, «|» separated (PowerShell 2.0 has no JSON parser).
     *
     * @return list<string>
     */
    public function lines(OneCAgentTask $task): array;

    /** The agent took the task. */
    public function claimed(OneCAgentTask $task): void;

    /**
     * The agent finished; returns a one-line summary for the panel («документ УТ-00000017»).
     *
     * @param  array{ok: bool, error: ?string, data: array<string, string>}  $result
     */
    public function finished(OneCAgentTask $task, array $result): ?string;

    /** A failed task went back to the queue. */
    public function requeued(OneCAgentTask $task): void;

    /** A task was cancelled before the agent did it. */
    public function cancelled(OneCAgentTask $task): void;

    /** Where the subject of the task is shown in the admin. */
    public function url(OneCAgentTask $task): ?string;
}
