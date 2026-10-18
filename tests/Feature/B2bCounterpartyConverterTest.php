<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Filament\Resources\CrmCounterparties\Pages\EditCrmCounterparty;
use App\Models\B2bClient;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\B2bCounterpartyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class B2bCounterpartyConverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_links_a_b2b_client_from_a_crm_counterparty(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'person',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Dzianis Hotta',
            'unp' => '123456789',
            'source' => 'email',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Dzianis Hotta',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'dzianis.hotta@gmail.com',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $profile = app(B2bCounterpartyConverter::class)->convert(
            $counterparty,
            'dzianis.hotta@gmail.com',
        );

        $this->assertSame($counterparty->id, $profile->counterparty_id);
        $this->assertSame('pending', $profile->status);
        $this->assertSame('Dzianis Hotta', $profile->company_name);
        $this->assertSame('dzianis.hotta@gmail.com', $profile->contact_email);
        $this->assertSame($profile->user_id, $contact->fresh()->user_id);
        $this->assertSame(ClientType::Wholesale, $profile->user->client_type);
        $this->assertFalse($profile->user->b2b_approved);
    }

    public function test_conversion_reuses_an_existing_site_account_and_does_not_create_duplicates(): void
    {
        $user = User::factory()->create(['email' => 'partner@example.com']);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'ООО Партнёр',
            'source' => 'manual',
        ]);
        $converter = app(B2bCounterpartyConverter::class);

        $first = $converter->convert($counterparty, 'partner@example.com');
        $second = $converter->convert($counterparty->fresh(), 'partner@example.com');

        $this->assertSame($user->id, $first->user_id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, B2bClient::query()->where('counterparty_id', $counterparty->id)->count());
        $this->assertSame(1, User::query()->where('email', 'partner@example.com')->count());
    }

    public function test_admin_can_create_and_then_open_a_b2b_client_from_the_counterparty_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'unclassified',
            'business_unit' => 'retail',
            'status' => 'active',
            'name' => 'ООО Новый партнёр',
            'source' => 'manual',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditCrmCounterparty::class, ['record' => $counterparty->getRouteKey()])
            ->assertActionVisible('createB2bClient')
            ->assertActionHidden('openB2bClient')
            ->callAction('createB2bClient', ['email' => 'new-partner@example.com'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('createB2bClient')
            ->assertActionVisible('openB2bClient');

        $profile = B2bClient::query()->sole();

        $this->assertSame($counterparty->id, $profile->counterparty_id);
        $this->assertSame('b2b_partner', $counterparty->fresh()->classification);
        $this->assertSame('wholesale', $counterparty->fresh()->business_unit);
    }
}
