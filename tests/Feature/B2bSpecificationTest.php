<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bSpecification;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\B2bSpecificationItemDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class B2bSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_client_can_upload_private_specification_files(): void
    {
        Storage::fake('local');
        [$user, $client] = $this->b2bClient();

        $response = $this->actingAs($user)->post(route('b2b.cabinet.specifications.store'), [
            'title' => 'Котельная административного здания',
            'comment' => 'Допустимы аналоги, поставка в ноябре.',
            'files' => [
                UploadedFile::fake()->create(
                    'specification.xlsx',
                    24,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
                UploadedFile::fake()->create('scheme.pdf', 32, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('b2b.cabinet', ['section' => 'specifications']));
        $specification = B2bSpecification::query()->with('attachments')->sole();
        $this->assertSame($client->id, $specification->b2b_client_id);
        $this->assertSame('submitted', $specification->status);
        $this->assertCount(2, $specification->attachments);
        $this->assertStringStartsWith('SP-', $specification->reference);
        foreach ($specification->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertSame(64, strlen($attachment->sha256));
            $this->assertStringStartsWith("b2b-specifications/{$client->id}/{$specification->id}/", $attachment->path);
        }
    }

    public function test_specification_attachment_is_available_only_to_owner_or_manager(): void
    {
        Storage::fake('local');
        [$owner, $client] = $this->b2bClient();
        [$otherClient] = $this->b2bClient('other@example.test');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $specification = B2bSpecification::query()->create([
            'b2b_client_id' => $client->id,
            'user_id' => $owner->id,
            'title' => 'Насосная группа',
            'status' => 'submitted',
        ]);
        Storage::disk('local')->put('b2b-specifications/test/spec.pdf', 'private-content');
        $attachment = $specification->attachments()->create([
            'disk' => 'local',
            'path' => 'b2b-specifications/test/spec.pdf',
            'original_name' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 15,
            'sha256' => hash('sha256', 'private-content'),
        ]);

        $this->actingAs($owner)
            ->get(route('b2b.cabinet.specifications.attachment', $attachment))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($manager)
            ->get(route('b2b.cabinet.specifications.attachment', $attachment))
            ->assertOk();
        $this->actingAs($otherClient)
            ->get(route('b2b.cabinet.specifications.attachment', $attachment))
            ->assertNotFound();
    }

    public function test_disabled_specification_service_rejects_upload(): void
    {
        Storage::fake('local');
        [$user, $client] = $this->b2bClient();
        $client->update(['specification_service_enabled' => false]);

        $this->actingAs($user)->post(route('b2b.cabinet.specifications.store'), [
            'title' => 'Проект',
            'files' => [UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf')],
        ])->assertForbidden();

        $this->assertDatabaseCount('b2b_specifications', 0);
    }

    public function test_client_sees_public_manager_message_but_not_internal_note(): void
    {
        [$user, $client] = $this->b2bClient();
        B2bSpecification::query()->create([
            'b2b_client_id' => $client->id,
            'user_id' => $user->id,
            'title' => 'Система отопления склада',
            'status' => 'clarification',
            'client_message' => 'Уточните требуемую мощность котла.',
            'manager_note' => 'Проверить маржу перед отправкой КП.',
        ]);

        $this->actingAs($user)
            ->get(route('b2b.cabinet', ['section' => 'specifications']))
            ->assertOk()
            ->assertSee('Уточните требуемую мощность котла.')
            ->assertDontSee('Проверить маржу перед отправкой КП.');
    }

    public function test_excel_rows_are_extracted_and_matched_by_exact_sku(): void
    {
        Storage::fake('local');
        [$user] = $this->b2bClient();
        $category = Category::query()->create(['name' => 'Спецификации', 'slug' => 'spec-items', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос циркуляционный TEST 25-60',
            'slug' => 'test-pump-25-60',
            'sku' => 'PUMP-2560',
            'price' => 250,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $path = $this->spreadsheetFile([
            ['Артикул', 'Наименование', 'Количество', 'Ед. изм.'],
            ['PUMP-2560', 'Насос по проекту', 3, 'шт.'],
            ['UNKNOWN-1', 'Неизвестная позиция', 2, 'шт.'],
        ]);

        try {
            $this->actingAs($user)->post(route('b2b.cabinet.specifications.store'), [
                'title' => 'Спецификация из Excel',
                'files' => [new UploadedFile($path, 'specification.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])->assertRedirect();
        } finally {
            @unlink($path);
        }

        $specification = B2bSpecification::query()->with('items')->sole();
        $this->assertSame('parsed', $specification->parsing_status);
        $this->assertCount(2, $specification->items);
        $matched = $specification->items->firstWhere('source_sku', 'PUMP-2560');
        $this->assertSame('matched', $matched->status);
        $this->assertSame($product->id, $matched->product_id);
        $this->assertSame('3.000', $matched->quantity);
        $this->assertSame('unmatched', $specification->items->firstWhere('source_sku', 'UNKNOWN-1')->status);
    }

    public function test_owner_can_add_matched_specification_items_to_personal_b2b_cart(): void
    {
        [$user, $client] = $this->b2bClient();
        $category = Category::query()->create(['name' => 'B2B склад', 'slug' => 'b2b-spec-cart', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар из спецификации',
            'slug' => 'specification-cart-product',
            'sku' => 'SPEC-1',
            'price' => 150,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'specification-cart-supplier',
            'name' => 'Оптовый склад',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'specification-cart-source',
            'name' => 'Оптовый склад',
            'is_active' => true,
            'settings' => [
                'b2b_enabled' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'b2b_category_ids' => [$category->id],
                'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                'warehouse_label' => 'Основной склад',
            ],
        ]);
        $offer = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'SPEC-1',
            'name' => $product->name,
            'price' => 100,
            'price_byn' => 100,
            'stock_quantity' => 10,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);
        $client->update(['allowed_integration_source_ids' => [$source->id]]);
        $specification = B2bSpecification::query()->create([
            'b2b_client_id' => $client->id,
            'user_id' => $user->id,
            'title' => 'Корзина из спецификации',
            'status' => 'submitted',
        ]);
        $specification->items()->create([
            'source_sku' => 'SPEC-1',
            'source_name' => $product->name,
            'quantity' => 2,
            'status' => 'matched',
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->post(route('b2b.cabinet.specifications.cart', $specification))
            ->assertRedirect(route('cart'))
            ->assertSessionHas('cart.'.$product->id.'.integration_product_id', $offer->id)
            ->assertSessionHas('cart.'.$product->id.'.quantity', 2)
            ->assertSessionHas('cart.'.$product->id.'.price', 100.0);
    }

    public function test_fuzzy_candidate_requires_manager_confirmation_and_rejects_conflicting_model_numbers(): void
    {
        Storage::fake('local');
        [$user] = $this->b2bClient();
        $category = Category::query()->create(['name' => 'Насосы', 'slug' => 'spec-candidate-pumps', 'is_active' => true]);
        $candidate = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос циркуляционный Wilo Star-RS 25/6',
            'slug' => 'wilo-star-rs-25-6-candidate',
            'sku' => 'WILO-256',
            'price' => 350,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $conflicting = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос циркуляционный Wilo Star-RS 25/4',
            'slug' => 'wilo-star-rs-25-4-conflict',
            'sku' => 'WILO-254',
            'price' => 330,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $path = $this->spreadsheetFile([
            ['Наименование', 'Количество'],
            ['Циркуляционный насос Wilo Star RS 25-6 для отопления', 2],
        ]);

        try {
            $this->actingAs($user)->post(route('b2b.cabinet.specifications.store'), [
                'title' => 'Кандидат для проверки',
                'files' => [new UploadedFile($path, 'candidate.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])->assertRedirect();
        } finally {
            @unlink($path);
        }

        $item = B2bSpecification::query()->sole()->items()->sole();
        $this->assertSame('suggested', $item->status);
        $this->assertNull($item->product_id);
        $this->assertSame($candidate->id, $item->topCandidate()['product_id']);
        $this->assertNotContains($conflicting->id, collect($item->candidates)->pluck('product_id')->all());

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->assertTrue(app(B2bSpecificationItemDecision::class)->confirmTopCandidate($item, $manager));
        $item->refresh();
        $this->assertSame('matched', $item->status);
        $this->assertSame($candidate->id, $item->product_id);
        $this->assertSame($manager->id, $item->reviewed_by);
        $this->assertSame('manager_candidate', $item->match_reason);
        $this->assertNotNull($item->reviewed_at);
    }

    public function test_client_revision_creates_immutable_version_and_diff_without_mixing_old_rows(): void
    {
        Storage::fake('local');
        [$user] = $this->b2bClient();
        $initialPath = $this->spreadsheetFile([
            ['Артикул', 'Наименование', 'Количество'],
            ['A-1', 'Клапан первый', 1],
            ['B-2', 'Клапан второй', 2],
        ]);
        $revisionPath = $this->spreadsheetFile([
            ['Артикул', 'Наименование', 'Количество'],
            ['A-1', 'Клапан первый', 3],
            ['C-3', 'Клапан третий', 4],
        ]);

        try {
            $this->actingAs($user)->post(route('b2b.cabinet.specifications.store'), [
                'title' => 'Версионная спецификация',
                'files' => [new UploadedFile($initialPath, 'version-1.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])->assertRedirect();

            $specification = B2bSpecification::query()->sole();
            $this->actingAs($user)->post(route('b2b.cabinet.specifications.revisions.store', $specification), [
                'revision_note' => 'Изменили количество и одну позицию.',
                'files' => [new UploadedFile($revisionPath, 'version-2.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])->assertRedirect(route('b2b.cabinet', ['section' => 'specifications']));
        } finally {
            @unlink($initialPath);
            @unlink($revisionPath);
        }

        $specification->refresh();
        $versions = $specification->versions()->orderBy('number')->get();
        $this->assertCount(2, $versions);
        $this->assertSame($versions[1]->id, $specification->current_version_id);
        $this->assertSame('Изменили количество и одну позицию.', $versions[1]->note);
        $this->assertSame([
            'added' => 1,
            'removed' => 1,
            'changed' => 1,
            'unchanged' => 0,
        ], $versions[1]->diff_summary);
        $this->assertCount(2, $specification->items()->get());
        $this->assertSame(['A-1', 'C-3'], $specification->items()->orderBy('source_sku')->pluck('source_sku')->all());
        $this->assertCount(4, $specification->allItems()->get());
        $this->assertSame(2, $specification->allItems()->where('is_current', false)->count());

        $this->actingAs($user)
            ->get(route('b2b.cabinet', ['section' => 'specifications']))
            ->assertOk()
            ->assertSee('История редакций')
            ->assertSee('Редакция №2')
            ->assertSee('Изменили количество и одну позицию.')
            ->assertSee('version-1.xlsx')
            ->assertSee('version-2.xlsx');
    }

    public function test_other_client_cannot_upload_revision_to_someone_elses_specification(): void
    {
        Storage::fake('local');
        [$owner, $client] = $this->b2bClient();
        [$other] = $this->b2bClient('revision-attacker@example.test');
        $specification = B2bSpecification::query()->create([
            'b2b_client_id' => $client->id,
            'user_id' => $owner->id,
            'title' => 'Закрытая для другого клиента заявка',
            'status' => 'submitted',
        ]);

        $this->actingAs($other)->post(route('b2b.cabinet.specifications.revisions.store', $specification), [
            'files' => [UploadedFile::fake()->create('revision.xlsx', 10)],
        ])->assertNotFound();

        $this->assertDatabaseCount('b2b_specification_attachments', 0);
        $this->assertDatabaseCount('b2b_specification_versions', 0);
    }

    /** @return array{User, B2bClient} */
    private function b2bClient(?string $email = null): array
    {
        $user = User::factory()->create([
            'email' => $email ?: fake()->unique()->safeEmail(),
            'role' => 'client',
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Партнёр '.$user->id.'»',
            'unp' => str_pad((string) $user->id, 9, '1', STR_PAD_LEFT),
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'specification_service_enabled' => true,
        ]);

        return [$user->fresh(), $client->fresh()];
    }

    private function spreadsheetFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $temporary = tempnam(sys_get_temp_dir(), 'b2b-spec-');
        $path = $temporary.'.xlsx';
        @unlink($temporary);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
