<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Filament\Resources\B2bClients\B2bClientResource;
use App\Models\B2bClient;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\B2bClientWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bClientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_creates_a_separate_b2b_profile_without_a_crm_counterparty(): void
    {
        $user = User::factory()->create([
            'client_type' => ClientType::Wholesale,
            'company_name' => 'ООО Партнёр',
            'company_inn' => '192929714',
            'phone' => '+375291112233',
            'b2b_comment' => 'Нужны оптовые цены',
        ]);

        $client = app(B2bClientWorkflow::class)->registerApplication($user);

        $this->assertSame('pending', $client->status);
        $this->assertSame('wholesale', $client->partner_type);
        $this->assertSame('ООО Партнёр', $client->company_name);
        $this->assertSame('192929714', $client->unp);
        $this->assertSame(0, CrmCounterparty::query()->count());
        $this->assertFalse($user->fresh()->b2b_approved);
    }

    public function test_approval_and_pause_are_reflected_in_the_site_account(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $user = User::factory()->create([
            'client_type' => ClientType::Wholesale,
            'company_name' => 'ООО Клиент',
            'b2b_approved' => false,
        ]);
        $workflow = app(B2bClientWorkflow::class);
        $client = $workflow->registerApplication($user);

        $approved = $workflow->approve($client, $manager);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($manager->id, $approved->reviewed_by);
        $this->assertTrue($user->fresh()->b2b_approved);

        $paused = $workflow->pause($approved, $manager);

        $this->assertSame('paused', $paused->status);
        $this->assertFalse($user->fresh()->b2b_approved);
    }

    public function test_resubmission_updates_one_profile_instead_of_creating_duplicates(): void
    {
        $user = User::factory()->create([
            'client_type' => ClientType::Wholesale,
            'company_name' => 'ООО Первое название',
        ]);
        $workflow = app(B2bClientWorkflow::class);
        $workflow->registerApplication($user);

        $user->update(['company_name' => 'ООО Новое название']);
        $workflow->registerApplication($user->fresh());

        $this->assertSame(1, B2bClient::query()->where('user_id', $user->id)->count());
        $this->assertSame(
            'ООО Новое название',
            B2bClient::query()->where('user_id', $user->id)->value('company_name'),
        );
    }

    public function test_admin_has_a_dedicated_b2b_client_panel(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'client_type' => ClientType::Wholesale,
            'company_name' => 'ООО Отдельный B2B',
        ]);
        $client = app(B2bClientWorkflow::class)->registerApplication($user);

        $this->actingAs($admin)
            ->get(B2bClientResource::getUrl('index'))
            ->assertOk()
            ->assertSee('ООО Отдельный B2B');

        $this->actingAs($admin)
            ->get(B2bClientResource::getUrl('edit', ['record' => $client]))
            ->assertOk()
            ->assertSee('Коммерческие условия')
            ->assertSee('Аккаунт партнёра на сайте');
    }
}
