<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A task for the agent on the 1C server («Мост 1С»): what to do in 1C, its state and what 1C answered. */
class OneCAgentTask extends Model
{
    public const QUEUED = 'queued';

    public const SENDING = 'sending';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::QUEUED => 'В очереди',
        self::SENDING => 'Агент выполняет',
        self::DONE => 'Выполнено',
        self::FAILED => 'Ошибка',
        self::CANCELLED => 'Отменено',
    ];

    public const ACTIVE = [self::QUEUED, self::SENDING];

    protected $table = 'onec_agent_tasks';

    protected $fillable = [
        'type', 'subject_type', 'subject_id', 'title', 'status', 'attempts', 'created_by',
        'picked_at', 'finished_at', 'summary', 'result', 'error',
    ];

    protected $casts = [
        'picked_at' => 'datetime',
        'finished_at' => 'datetime',
        'result' => 'array',
        'attempts' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
