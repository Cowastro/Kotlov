<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailWorkAndCatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_taken_mail_stays_in_my_work_until_closed_even_without_a_reminder(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);
        $mailbox = CrmMailbox::query()->create(['name' => 'Work', 'email' => 'work@example.com', 'username' => 'work@example.com']);
        $threads = collect(['in_progress', 'waiting_customer', 'waiting_manager', 'closed', 'spam', 'draft'])->map(fn ($status) => CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id, 'thread_key' => 'work-'.$status,
            'participant_email' => 'work@example.com', 'subject' => 'Work '.$status,
            'assigned_user_id' => $manager->id, 'status' => $status,
        ]));
        $taken = CrmMailThread::query()->create(['mailbox_id' => $mailbox->id, 'thread_key' => 'new-take', 'participant_email' => 'new@example.com', 'status' => 'new']);
        $taken->takeFor($manager->id);
        $foreign = CrmMailThread::query()->create(['mailbox_id' => $mailbox->id, 'thread_key' => 'other-work', 'participant_email' => 'other@example.com', 'assigned_user_id' => $other->id, 'status' => 'in_progress']);
        $this->assertNull($taken->fresh()->next_follow_up_at);
        $this->assertSame(4, CrmMailThread::query()->assignedWork($manager->id)->count());
        Livewire::test(ListCrmMailThreads::class)->set('activeTab', 'my_work')
            ->assertCanSeeTableRecords([$taken, ...$threads->take(3)->all()])
            ->assertCanNotSeeTableRecords([$foreign, ...$threads->skip(3)->all()]);
        $taken->update(['status' => 'closed']);
        $this->assertFalse(CrmMailThread::query()->assignedWork($manager->id)->whereKey($taken->id)->exists());
    }

    public function test_catalog_search_finds_the_real_95_kw_model_without_area_questions_or_pro_noise(): void
    {
        $category = Category::query()->create(['name' => 'Пеллетные котлы', 'slug' => 'catalog-95-search', 'is_active' => true]);
        foreach (range(1, 260) as $number) {
            Product::query()->create(['category_id' => $category->id, 'name' => 'Котёл TIS PRO '.$number, 'slug' => 'noise-pro-'.$number, 'sku' => 'TIS-PRO-'.$number, 'is_active' => true, 'is_archived' => false]);
        }
        $target = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Пеллетный котёл KOTLOV XO Ceramic PRO 95 кВт с бункером 1,5 м³',
            'sku' => 'KOTLOV-XO-95-CP-1500', 'slug' => 'real-kotlov-95-search', 'price' => 25600,
            'is_active' => true, 'is_archived' => false, 'specs' => [['key' => 'Материал', 'value' => 'нержавеющая сталь']],
        ]);
        $service = app(QuickCommercialOfferService::class);
        $result = $service->catalogSelection('пеллетный Котел Kotlov pro 95');
        $this->assertTrue($result['ready']);
        $this->assertSame([$target->id], array_keys($result['suggestions']));
        $this->assertStringNotContainsString('Сначала уточните', $result['message']);
        $this->assertSame([$target->id], array_keys($service->catalogSearchProducts('KOTLOV XO 95')));
        $this->assertSame([$target->id], array_keys($service->catalogSearchProducts('KOTLOV-XO-95-CP-1500')));
        $this->assertSame([$target->id], array_keys($service->catalogSearchProducts('нержавеющая сталь KOTLOV 95')));
        $target->update(['is_archived' => true]);
        $this->assertSame([], $service->catalogSearchProducts('KOTLOV XO 95'));
    }
}
