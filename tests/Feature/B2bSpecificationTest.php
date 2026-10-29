<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bSpecification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
