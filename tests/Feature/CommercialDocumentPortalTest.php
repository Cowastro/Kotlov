<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CommercialDocumentPortalChallenge;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommercialDocumentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_does_not_activate_link_and_first_explicit_post_authorizes_device(): void
    {
        [$quote, $manager] = $this->quote();
        $portal = app(CommercialDocumentPortal::class);
        $link = $portal->publish($quote, $manager);

        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee('Открыть предложение');
        $this->assertDatabaseCount('commercial_document_portal_sessions', 0);
        $this->get(route('commercial-portal.show', $link->public_id))->assertForbidden();

        $this->post(route('commercial-portal.activate', $link->token_encrypted))
            ->assertRedirect(route('commercial-portal.show', $link->public_id));

        $this->get(route('commercial-portal.show', $link->public_id))
            ->assertOk()
            ->assertSee($quote->number)
            ->assertSee('Принять предложение')
            ->assertSee('Насос для портала');
        $this->assertDatabaseCount('commercial_document_portal_sessions', 1);
    }

    public function test_second_device_requires_email_verification_code(): void
    {
        Mail::fake();
        [$quote, $manager] = $this->quote();
        $portal = app(CommercialDocumentPortal::class);
        $link = $portal->publish($quote, $manager);
        $this->post(route('commercial-portal.activate', $link->token_encrypted));

        $this->flushSession();
        $this->post(route('commercial-portal.activate', $link->token_encrypted))
            ->assertRedirect();

        $this->assertSame(1, CommercialDocumentPortalChallenge::query()->count());
        $this->assertDatabaseCount('commercial_document_portal_sessions', 1);
    }

    public function test_admin_preview_does_not_activate_client_link_or_change_view_statistics(): void
    {
        [$quote, $manager] = $this->quote();
        $link = app(CommercialDocumentPortal::class)->publish($quote, $manager);

        $this->actingAs($manager)
            ->get(route('admin.commercial-documents.online-preview', $quote))
            ->assertOk()
            ->assertSee('Режим предпросмотра администратора')
            ->assertSee('Кнопки принятия, изменения и отказа доступны только клиенту');

        $this->assertDatabaseCount('commercial_document_portal_sessions', 0);
        $this->assertSame(0, $link->fresh()->open_count);
        $this->assertDatabaseMissing('commercial_document_portal_events', [
            'portal_link_id' => $link->id,
            'type' => 'proposal_viewed',
        ]);
    }

    public function test_accepting_offer_creates_exactly_one_order(): void
    {
        Event::fake([NewOrderCreated::class]);
        [$quote, $manager] = $this->quote();
        $portal = app(CommercialDocumentPortal::class);
        $link = $portal->publish($quote, $manager);
        $this->post(route('commercial-portal.activate', $link->token_encrypted));

        $this->post(route('commercial-portal.accept', $link->public_id), ['confirmation' => '1'])
            ->assertSessionHas('portal_success');
        $this->post(route('commercial-portal.accept', $link->public_id), ['confirmation' => '1'])
            ->assertSessionHas('portal_success');

        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame(1, Order::query()->where('commercial_document_id', $quote->id)->count());
        $this->assertDatabaseHas('commercial_document_portal_events', [
            'commercial_document_id' => $quote->id,
            'type' => 'proposal_accepted',
        ]);
    }

    public function test_publishing_captures_product_details_and_later_catalog_changes_do_not_replace_them(): void
    {
        [$quote, $manager, $product] = $this->quote(withProduct: true);
        app(CommercialDocumentPortal::class)->publish($quote, $manager);
        $snapshot = $quote->items()->firstOrFail()->portal_snapshot;

        $product->update(['short_description' => 'Описание изменилось после отправки']);

        $this->assertSame('Зафиксированное описание товара', $snapshot['short_description']);
        $this->assertNotEmpty($snapshot['captured_at']);
    }

    public function test_missing_product_relation_is_restored_from_exact_sku_and_catalog_link_is_shown(): void
    {
        [$quote, $manager, $product] = $this->quote(withProduct: true);
        $portal = app(CommercialDocumentPortal::class);
        $portal->publish($quote, $manager);
        $quote->items()->firstOrFail()->forceFill([
            'product_id' => null,
            'portal_snapshot' => null,
        ])->saveQuietly();

        $response = $this->actingAs($manager)
            ->get(route('admin.commercial-documents.online-preview', $quote))
            ->assertOk()
            ->assertSee('Посмотреть товар на сайте');

        $item = $quote->items()->firstOrFail();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(url('/portal-products/portal-pump'), $item->portal_snapshot['catalog_url']);
        $response->assertSee($item->portal_snapshot['catalog_url'], false);
    }

    public function test_explicit_snapshot_refresh_uses_current_product_brand_instead_of_stale_brand_attribute(): void
    {
        [$quote, $manager, $product] = $this->quote(withProduct: true);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'kotlov-pro'],
            ['name' => 'KOTLOV PRO', 'is_active' => true],
        );
        $product->update(['brand_id' => $brand->id]);

        $portal = app(CommercialDocumentPortal::class);
        $portal->publish($quote, $manager);

        $quote->items()->firstOrFail()->forceFill([
            'portal_snapshot' => [
                'attributes' => [['name' => 'Бренд', 'value' => 'КСТБ']],
                'captured_at' => now()->subDay()->toIso8601String(),
            ],
        ])->save();

        $portal->refreshSnapshot($quote);
        $snapshot = $quote->items()->firstOrFail()->portal_snapshot;

        $this->assertSame('KOTLOV PRO', $snapshot['brand']);
        $this->assertSame(
            [['name' => 'Бренд', 'value' => 'KOTLOV PRO']],
            array_values(array_filter($snapshot['attributes'], fn (array $attribute): bool => $attribute['name'] === 'Бренд')),
        );
        $this->assertDatabaseHas('commercial_document_portal_events', [
            'commercial_document_id' => $quote->id,
            'type' => 'snapshot_refreshed',
        ]);
    }

    public function test_expired_or_revoked_link_cannot_be_opened(): void
    {
        [$quote, $manager] = $this->quote();
        $portal = app(CommercialDocumentPortal::class);
        $link = $portal->publish($quote, $manager);
        $portal->revoke($link);

        $this->get($portal->url($link))->assertGone()->assertSee('недоступно');
    }

    public function test_manager_can_revoke_one_device_without_revoking_link(): void
    {
        [$quote, $manager] = $this->quote();
        $portal = app(CommercialDocumentPortal::class);
        $link = $portal->publish($quote, $manager);
        $session = $link->sessions()->create([
            'token_hash' => hash('sha256', 'manager-revoked-device'),
            'device_label' => 'Телефон · Chrome',
            'authorized_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $portal->revokeSession($session, $manager);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNull($link->fresh()->revoked_at);
        $this->assertDatabaseHas('commercial_document_portal_events', [
            'commercial_document_id' => $quote->id,
            'portal_link_id' => $link->id,
            'portal_session_id' => $session->id,
            'type' => 'device_revoked',
        ]);
    }

    public function test_published_offer_pdf_contains_online_block_and_remains_renderable(): void
    {
        [$quote, $manager] = $this->quote();
        app(CommercialDocumentPortal::class)->publish($quote, $manager);

        $pdf = app(CommercialDocumentPdf::class)->render($quote);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
    }

    private function quote(bool $withProduct = false): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = null;
        if ($withProduct) {
            $category = Category::query()->create([
                'name' => 'Портальные товары', 'slug' => 'portal-products', 'is_active' => true,
            ]);
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Насос для портала',
                'slug' => 'portal-pump',
                'sku' => 'PORTAL-1',
                'price' => 1200,
                'currency' => 'BYN',
                'short_description' => 'Зафиксированное описание товара',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'client_name' => 'Иван Клиент',
            'client_company' => 'ООО «Портал»',
            'client_email' => 'portal-client@example.com',
            'client_phone' => '+375291112233',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '5 рабочих дней',
            'valid_until' => today()->addDays(10),
        ]);
        $quote->items()->create([
            'product_id' => $product?->id,
            'name' => 'Насос для портала',
            'sku' => 'PORTAL-1',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        return [$quote->fresh('items'), $manager, $product];
    }
}
