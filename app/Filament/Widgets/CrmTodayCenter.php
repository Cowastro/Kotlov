<?php

namespace App\Filament\Widgets;

use App\Models\CrmReminder;
use App\Models\User;
use App\Services\Crm\AdminTodayDashboard;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Validation\Rule;

class CrmTodayCenter extends Widget
{
    public string $period = 'week';

    public string $status = 'open';

    public string $sortOrder = 'desc';

    public string $reminderTitle = '';

    public string $reminderNote = '';

    public ?string $reminderDueAt = null;

    public ?int $reminderAssignedUserId = null;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.crm-today-center';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    public function saveQuickReminder(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isManager(), 403);

        $data = $this->validate([
            'reminderTitle' => ['required', 'string', 'max:180'],
            'reminderNote' => ['nullable', 'string', 'max:2000'],
            'reminderDueAt' => ['nullable', 'date'],
            'reminderAssignedUserId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                    ->where('is_active', true)),
            ],
        ]);

        $assignedUserId = $user->isAdmin()
            ? ($data['reminderAssignedUserId'] ?: null)
            : $user->id;

        CrmReminder::query()->create([
            'title' => trim($data['reminderTitle']),
            'note' => filled($data['reminderNote']) ? trim($data['reminderNote']) : null,
            'due_at' => filled($data['reminderDueAt'])
                ? Carbon::parse($data['reminderDueAt'], 'Europe/Minsk')->utc()
                : null,
            'assigned_user_id' => $assignedUserId,
            'created_by' => $user->id,
        ]);

        $this->reset(['reminderTitle', 'reminderNote', 'reminderDueAt']);
        $this->reminderAssignedUserId = $user->isAdmin() ? null : $user->id;

        Notification::make()->success()->title('Напоминание добавлено')->send();
    }

    public function completeReminder(int $reminderId): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isManager(), 403);

        $reminder = CrmReminder::query()
            ->whereNull('completed_at')
            ->when(
                $user->isOperationalManager(),
                fn ($query) => $query->where('assigned_user_id', $user->id),
            )
            ->findOrFail($reminderId);

        $reminder->update([
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        Notification::make()->success()->title('Напоминание выполнено')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $viewer = auth()->user();

        return app(AdminTodayDashboard::class)->data(
            period: $this->period,
            status: $this->status,
            sort: $this->sortOrder,
            viewer: $viewer instanceof User ? $viewer : null,
        );
    }
}
