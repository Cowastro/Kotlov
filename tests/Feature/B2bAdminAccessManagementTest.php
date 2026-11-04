<?php

namespace Tests\Feature;

use App\Filament\Resources\B2bClients\Pages\EditB2bClient;
use App\Filament\Resources\B2bClients\RelationManagers\InvitationsRelationManager;
use App\Filament\Resources\B2bClients\RelationManagers\MembersRelationManager;
use App\Models\B2bClient;
use App\Models\User;
use App\Services\B2bTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class B2bAdminAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_linked_accounts_and_can_manage_their_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $owner = User::factory()->create(['name' => 'Владелец СБГ', 'email' => 'owner-sbg@example.by']);
        $employee = User::factory()->create(['name' => 'Виктор Колтун', 'email' => 'viktor@example.by']);
        $client = B2bClient::query()->create([
            'user_id' => $owner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «СБГ бай»',
        ]);
        $member = $client->members()->create([
            'user_id' => $employee->id,
            'role' => 'branch_operator',
            'is_active' => true,
            'invited_by' => $admin->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditB2bClient::class])
            ->assertCanSeeTableRecords([$client->members()->where('role', 'owner')->firstOrFail(), $member])
            ->assertSee('Виктор Колтун')
            ->assertSee('Филиал: закупки и продажи')
            ->callTableAction('changeRole', $member, ['role' => 'buyer'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('revokeAccess', $member)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('b2b_client_members', [
            'id' => $member->id,
            'role' => 'buyer',
            'is_active' => false,
        ]);

        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditB2bClient::class])
            ->callTableAction('restoreAccess', $member->fresh())
            ->assertHasNoTableActionErrors();

        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_admin_sees_pending_accepted_revoked_and_expired_invitations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $owner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $owner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «СБГ бай»',
        ]);
        [$pending] = app(B2bTeamService::class)->invite($client, $admin, 'pending@example.by', 'branch_operator');
        [$accepted] = app(B2bTeamService::class)->invite($client, $admin, 'accepted@example.by', 'buyer');
        $accepted->update(['accepted_at' => now(), 'accepted_by' => $owner->id]);
        [$revoked] = app(B2bTeamService::class)->invite($client, $admin, 'revoked@example.by', 'viewer');
        $revoked->update(['revoked_at' => now()]);
        [$expired] = app(B2bTeamService::class)->invite($client, $admin, 'expired@example.by', 'accountant');
        $expired->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($admin);
        Livewire::test(InvitationsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditB2bClient::class])
            ->assertCanSeeTableRecords([$pending, $accepted, $revoked, $expired])
            ->assertSee('Ожидает')
            ->assertSee('Принято')
            ->assertSee('Отозвано')
            ->assertSee('Просрочено')
            ->callTableAction('revokeInvitation', $pending)
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($pending->fresh()->revoked_at);
    }
}
