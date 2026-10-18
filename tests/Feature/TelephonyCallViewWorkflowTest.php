<?php

namespace Tests\Feature;

use App\Filament\Resources\TelephonyCalls\Pages\ViewTelephonyCall;
use App\Models\TelephonyCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TelephonyCallViewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_call_does_not_change_it_and_shows_workflow_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $call = $this->createCall('call-view-actions');

        $this->actingAs($admin);

        Livewire::test(ViewTelephonyCall::class, ['record' => $call->getRouteKey()])
            ->assertActionVisible('callClient')
            ->assertActionVisible('takeInWork')
            ->assertActionVisible('scheduleFollowUp')
            ->assertActionVisible('saveResult')
            ->assertActionVisible('manage');

        $call->refresh();

        $this->assertSame('callback_required', $call->handling_status);
        $this->assertNull($call->assigned_user_id);
    }

    public function test_manager_can_take_call_in_work_and_becomes_responsible(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $call = $this->createCall('call-take-in-work');

        $this->actingAs($manager);

        Livewire::test(ViewTelephonyCall::class, ['record' => $call->getRouteKey()])
            ->callAction('takeInWork')
            ->assertNotified('Звонок взят в работу');

        $call->refresh();

        $this->assertSame('in_progress', $call->handling_status);
        $this->assertSame($manager->id, $call->assigned_user_id);
    }

    public function test_manager_can_assign_call_to_another_responsible_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $responsible = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $call = $this->createCall('call-assign-responsible');

        $this->actingAs($admin);

        Livewire::test(ViewTelephonyCall::class, ['record' => $call->getRouteKey()])
            ->callAction('manage', [
                'handling_status' => 'in_progress',
                'assigned_user_id' => $responsible->id,
                'manager_note' => 'Менеджер уточняет наличие и цену.',
            ])
            ->assertNotified('Статус и ответственный обновлены');

        $call->refresh();

        $this->assertSame('in_progress', $call->handling_status);
        $this->assertSame($responsible->id, $call->assigned_user_id);
        $this->assertSame('Менеджер уточняет наличие и цену.', $call->manager_note);
    }

    public function test_manager_can_schedule_callback_from_call_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $call = $this->createCall('call-schedule-callback');

        $this->actingAs($manager);

        Livewire::test(ViewTelephonyCall::class, ['record' => $call->getRouteKey()])
            ->callAction('scheduleFollowUp', [
                'next_follow_up_at' => '2026-10-19 15:30:00',
                'manager_note' => 'Перезвонить после согласования модели.',
            ])
            ->assertNotified('Повторный звонок запланирован');

        $call->refresh();

        $this->assertSame('callback_required', $call->handling_status);
        $this->assertSame('callback_later', $call->manager_outcome);
        $this->assertSame($manager->id, $call->assigned_user_id);
        $this->assertSame(
            '2026-10-19 15:30:00',
            $call->next_follow_up_at->timezone('Europe/Minsk')->format('Y-m-d H:i:s'),
        );
    }

    private function createCall(string $uuid): TelephonyCall
    {
        return TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => $uuid,
            'direction' => 'inbound',
            'customer_phone' => '375297064324',
            'status' => 'missed',
            'handling_status' => 'callback_required',
            'started_at' => '2026-10-18 08:45:54',
        ]);
    }
}
