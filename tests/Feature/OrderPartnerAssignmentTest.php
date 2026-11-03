<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Actions\AssignOrderToPartnerAction;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\PartnerOrderOfferedMail;
use App\Models\B2bClient;
use App\Models\B2bSavedCart;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerBranch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPartnerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_centre_hands_an_order_to_a_partner_who_accepts_it_and_gets_a_purchase_basket(): void
    {
        Mail::fake();
        [$order, $product] = $this->order('Борисов');
        $minsk = $this->city('minsk', 'Минск');
        $borisov = $this->city('borisov', 'Борисов');
        $partner = $this->partner('ООО «СБГ бай»', 'partner@sbg.test');
        $this->branch($partner, $minsk, 'ООО «СБГ бай»');
        $pechkin = $this->branch($partner, $borisov, 'Магазин «Печкiн»');
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $order->update(['manager_id' => $manager->id]);

        // The point in the customer's city is offered first.
        $this->assertSame($pechkin->id, array_key_first(AssignOrderToPartnerAction::options($order)));

        $this->actingAs($manager);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('assignToPartner', data: ['partner_branch_id' => $pechkin->id, 'note' => 'Клиент ждёт звонка до 18:00'])
            ->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame('offered', $order->partner_status);
        $this->assertSame($pechkin->id, $order->partner_branch_id);
        Mail::assertQueued(PartnerOrderOfferedMail::class, fn ($mail): bool => $mail->hasTo('partner@sbg.test'));
        $this->assertTrue($order->managerComments()->where('body', 'like', 'Заказ передан партнёру: Магазин «Печкiн»%')->exists());

        // Before accepting, the partner sees the lines but not the customer's contacts.
        $this->actingAs($partner->user)->get(route('b2b.cabinet', ['section' => 'leads']))
            ->assertOk()
            ->assertSee('Заказы клиентов')
            ->assertSee('Заказ '.$order->number)
            ->assertSee('Котёл для партнёра')
            ->assertSee('Клиент ждёт звонка до 18:00')
            ->assertSee('Принять заказ')
            ->assertDontSee('+375 (29) 555-44-33');

        $this->actingAs($partner->user)->post(route('b2b.cabinet.client-orders.accept', $order))
            ->assertRedirect(route('b2b.cabinet', ['section' => 'leads']));
        $this->assertSame('accepted', $order->fresh()->partner_status);
        $cart = B2bSavedCart::query()->sole();
        $this->assertSame($partner->user_id, $cart->user_id);
        $this->assertSame([['id' => $product->id, 'quantity' => 2]], $cart->items);
        $this->assertTrue($manager->notifications()->get()->contains(fn ($n) => str_contains((string) ($n->data['title'] ?? ''), 'Партнёр принял заказ')));

        $this->actingAs($partner->user)->get(route('b2b.cabinet', ['section' => 'leads']))
            ->assertOk()->assertSee('+375 (29) 555-44-33')->assertSee('Оформить закупку');

        // A second answer is refused.
        $this->actingAs($partner->user)->post(route('b2b.cabinet.client-orders.decline', $order), ['reason' => 'Поздно'])
            ->assertSessionHasErrors('order');
    }

    public function test_partner_can_decline_with_a_reason_and_strangers_cannot_touch_the_order(): void
    {
        Mail::fake();
        [$order] = $this->order('Минск');
        $minsk = $this->city('minsk', 'Минск');
        $partner = $this->partner('ООО «СБГ бай»', 'partner@sbg.test');
        $branch = $this->branch($partner, $minsk, 'ООО «СБГ бай»');
        $order->forceFill(['partner_branch_id' => $branch->id, 'partner_status' => 'offered', 'partner_assigned_at' => now()])->save();

        $stranger = $this->partner('ООО «Чужой»', 'other@test.by');
        $this->actingAs($stranger->user)->post(route('b2b.cabinet.client-orders.accept', $order))->assertNotFound();

        $this->actingAs($partner->user)->post(route('b2b.cabinet.client-orders.decline', $order), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs($partner->user)->post(route('b2b.cabinet.client-orders.decline', $order), ['reason' => 'Нет монтажника на эту дату'])
            ->assertRedirect(route('b2b.cabinet', ['section' => 'leads']));

        $order->refresh();
        $this->assertSame('declined', $order->partner_status);
        $this->assertSame('Нет монтажника на эту дату', $order->partner_note);
        $this->assertSame(0, B2bSavedCart::query()->count());
    }

    private function order(string $city): array
    {
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'opa-kotly', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Котёл для партнёра', 'slug' => 'opa-kotel',
            'sku' => 'OPA-1', 'price' => 1500, 'is_active' => true, 'is_archived' => false,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-PARTNER-1', 'status' => 'new', 'customer_name' => 'Иван Клиент',
            'customer_phone' => '+375 (29) 555-44-33', 'delivery_type' => 'courier', 'delivery_city' => $city,
            'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 3000, 'total' => 3000,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'product_sku' => $product->sku, 'price' => 1500, 'quantity' => 2, 'total' => 3000,
        ]);

        return [$order, $product];
    }

    private function partner(string $company, string $email): B2bClient
    {
        $user = User::factory()->create(['email' => $email, 'client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);

        return B2bClient::query()->create([
            'user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => $company, 'contact_name' => $user->name, 'contact_email' => $email,
        ])->load('user');
    }

    private function branch(B2bClient $partner, City $city, string $name): PartnerBranch
    {
        return PartnerBranch::query()->create([
            'b2b_client_id' => $partner->id, 'city_id' => $city->id, 'name' => $name, 'phones' => ['+375 (29) 000-00-00'],
        ]);
    }

    private function city(string $slug, string $name): City
    {
        return City::query()->create([
            'slug' => $slug, 'name' => $name, 'name_in' => 'в '.$name, 'name_title' => $name,
            'region' => $name, 'is_active' => true, 'storefront_enabled' => true,
        ]);
    }
}
