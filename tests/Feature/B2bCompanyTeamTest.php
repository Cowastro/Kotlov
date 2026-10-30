<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bClientMember;
use App\Models\Order;
use App\Models\User;
use App\Services\B2bCheckoutTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bCompanyTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_registered_employee_and_employee_sees_company_orders(): void
    {
        [$owner, $client] = $this->company();
        $buyer = User::factory()->create([
            'email' => 'buyer@example.by',
            'role' => 'client',
            'is_active' => true,
            'client_type' => 'retail',
            'b2b_approved' => false,
        ]);
        $order = $this->order($owner, 'B2B-COMPANY-ORDER');
        $order->update(['status' => 'confirmed', 'availability_confirmed_at' => now()]);

        $this->actingAs($owner)->post(route('b2b.cabinet.team.store'), [
            'email' => $buyer->email,
            'role' => 'buyer',
        ])->assertRedirect(route('b2b.cabinet', ['section' => 'team']));

        $this->assertDatabaseHas('b2b_client_members', [
            'b2b_client_id' => $client->id,
            'user_id' => $buyer->id,
            'role' => 'buyer',
            'is_active' => true,
        ]);
        $this->assertTrue($buyer->fresh()->isB2B());
        $terms = app(B2bCheckoutTerms::class)->evaluate($buyer->fresh(), [
            10 => ['id' => 10, 'name' => 'Товар', 'quantity' => 1, 'price' => 100, 'pricing_type' => 'b2b'],
        ]);
        $this->assertTrue($terms['can_checkout']);
        $this->assertSame($client->id, $terms['terms_snapshot']['b2b_client_id']);
        $this->assertSame($owner->id, $terms['terms_snapshot']['company_account_user_id']);
        $this->assertSame($buyer->id, $terms['terms_snapshot']['placed_by_user_id']);
        $this->assertSame('buyer', $terms['terms_snapshot']['placed_by_company_role']);

        $this->actingAs($buyer->fresh())
            ->get(route('b2b.cabinet', ['section' => 'orders']))
            ->assertOk()
            ->assertSeeText('B2B-COMPANY-ORDER')
            ->assertSeeText('Закупщик')
            ->assertDontSeeText('Сотрудники и роли');

        $this->actingAs($buyer->fresh())
            ->put(route('b2b.cabinet.profile'), [])
            ->assertForbidden();
        $this->actingAs($buyer->fresh())
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('B2B-COMPANY-ORDER');

        $buyerOrder = $this->order($buyer, 'B2B-FORMER-EMPLOYEE');
        $this->assertSame(2, $client->fresh()->orders()->count());
        $membership = B2bClientMember::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->actingAs($owner)
            ->delete(route('b2b.cabinet.team.destroy', $membership))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'team']));
        $this->assertFalse($membership->fresh()->is_active);
        $this->assertFalse($buyer->fresh()->isB2B());
        $this->actingAs($owner)
            ->get(route('b2b.cabinet', ['section' => 'orders']))
            ->assertOk()
            ->assertSeeText($buyerOrder->number);
    }

    public function test_accountant_can_view_finance_but_cannot_open_catalog_or_checkout(): void
    {
        [$owner, $client] = $this->company();
        $accountant = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
            'client_type' => 'retail',
            'b2b_approved' => false,
        ]);
        $client->members()->create([
            'user_id' => $accountant->id,
            'role' => 'accountant',
            'is_active' => true,
            'invited_by' => $owner->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($accountant)
            ->get(route('b2b.cabinet', ['section' => 'reconciliation']))
            ->assertOk()
            ->assertSeeText('Сверка взаиморасчётов')
            ->assertDontSeeText('Сохранённые корзины');
        $this->actingAs($accountant)
            ->get(route('account'))
            ->assertOk()
            ->assertSeeText('ООО «Команда»')
            ->assertDontSeeText('Открыть оптовый каталог');
        $this->actingAs($accountant)->get(route('b2b.catalog'))->assertForbidden();

        $checkout = app(B2bCheckoutTerms::class)->evaluate($accountant, [
            10 => ['id' => 10, 'name' => 'Товар', 'quantity' => 1, 'price' => 100, 'pricing_type' => 'b2b'],
        ]);
        $this->assertFalse($checkout['can_checkout']);
        $this->assertStringContainsString('не позволяет оформлять заказы', $checkout['message']);
    }

    public function test_deferred_credit_limit_is_shared_by_all_company_users(): void
    {
        [$owner, $client] = $this->company();
        $buyer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $client->update(['payment_deferment_days' => 14, 'credit_limit' => 1000]);
        $client->members()->create([
            'user_id' => $buyer->id,
            'role' => 'buyer',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $this->order($buyer, 'B2B-BUYER-CREDIT', 'invoice_deferred', 400);

        $credit = app(B2bCheckoutTerms::class)->creditSummary($client->fresh());

        $this->assertSame(400.0, $credit['exposure']);
        $this->assertSame(600.0, $credit['available']);
    }

    public function test_owner_cannot_be_removed_and_member_cannot_join_two_companies(): void
    {
        [$owner, $client] = $this->company();
        [$otherOwner] = $this->company('other-owner@example.by', 'ООО «Другая»');
        $ownerMembership = B2bClientMember::query()->where('b2b_client_id', $client->id)->where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('b2b.cabinet.team.destroy', $ownerMembership))
            ->assertNotFound();

        $this->actingAs($owner)->post(route('b2b.cabinet.team.store'), [
            'email' => $otherOwner->email,
            'role' => 'buyer',
        ])->assertSessionHasErrors('email');
    }

    /** @return array{User, B2bClient} */
    private function company(string $email = 'owner@example.by', string $name = 'ООО «Команда»'): array
    {
        $owner = User::factory()->create([
            'email' => $email,
            'role' => 'client',
            'is_active' => true,
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'company_name' => $name,
            'company_inn' => fake()->unique()->numerify('19#######'),
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $owner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => $name,
            'unp' => $owner->company_inn,
            'contact_name' => $owner->name,
            'contact_email' => $owner->email,
        ]);

        return [$owner->fresh(), $client->fresh()];
    }

    private function order(User $user, string $number, string $payment = 'invoice', float $total = 100): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'number' => $number,
            'status' => 'new',
            'customer_name' => $user->name,
            'customer_phone' => '+375290000000',
            'customer_email' => $user->email,
            'delivery_type' => 'pickup',
            'payment_type' => $payment,
            'payment_status' => 'pending',
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
