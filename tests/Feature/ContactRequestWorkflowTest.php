<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_request_does_not_change_its_status_and_shows_workflow_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $request = ContactRequest::query()->create([
            'name' => 'Тестовый клиент',
            'phone' => '+375290000000',
            'message' => 'Нужна консультация',
            'status' => 'new',
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewContactRequest::class, ['record' => $request->getRouteKey()])
            ->assertActionVisible('callClient')
            ->assertActionVisible('takeInWork')
            ->assertActionVisible('scheduleFollowUp')
            ->assertActionVisible('complete')
            ->assertActionVisible('manage');

        $this->assertSame('new', $request->fresh()->status);
    }

    public function test_manager_can_take_request_in_work_and_becomes_responsible(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $request = ContactRequest::query()->create([
            'name' => 'Клиент',
            'phone' => '+375290000001',
            'message' => 'Перезвоните',
            'status' => 'new',
        ]);

        $this->actingAs($manager);

        Livewire::test(ViewContactRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('takeInWork')
            ->assertNotified('Обращение взято в работу');

        $request->refresh();

        $this->assertSame('in_progress', $request->status);
        $this->assertSame($manager->id, $request->responsible_user_id);
    }
}
