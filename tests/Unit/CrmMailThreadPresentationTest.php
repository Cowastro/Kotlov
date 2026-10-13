<?php

namespace Tests\Unit;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailThread;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmMailThreadPresentationTest extends TestCase
{
    /** @return array<string, array{int, string, string, string}> */
    public static function states(): array
    {
        return [
            'unread has priority over workflow status' => [2, 'closed', 'Непрочитано: 2', 'warning'],
            'new but already opened' => [0, 'new', 'Новое · прочитано', 'danger'],
            'closed and opened' => [0, 'closed', 'Закрыто · прочитано', 'success'],
            'regular opened thread' => [0, 'in_progress', 'Прочитано', 'gray'],
        ];
    }

    #[DataProvider('states')]
    public function test_mail_read_state_is_clear(
        int $unreadCount,
        string $status,
        string $label,
        string $color,
    ): void {
        $thread = new CrmMailThread([
            'unread_count' => $unreadCount,
            'status' => $status,
        ]);

        $this->assertSame($label, CrmMailThreadResource::mailReadLabel($thread));
        $this->assertSame($color, CrmMailThreadResource::mailReadColor($thread));
    }

    public function test_unread_and_closed_rows_have_different_emphasis(): void
    {
        $unread = new CrmMailThread(['unread_count' => 1, 'status' => 'new']);
        $closed = new CrmMailThread(['unread_count' => 0, 'status' => 'closed']);

        $this->assertStringContainsString('warning', CrmMailThreadResource::mailRowClasses($unread));
        $this->assertStringContainsString('opacity-70', CrmMailThreadResource::mailRowClasses($closed));
    }
}
