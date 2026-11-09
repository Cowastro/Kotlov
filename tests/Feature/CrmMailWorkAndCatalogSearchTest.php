<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use Filament\Facades\Filament;
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

    public function test_work_queue_filters_by_owner_and_reason_and_sorts_by_responsible_name(): void
    {
        $manager = User::factory()->create(['name' => 'Zeta Manager', 'role' => 'manager', 'is_active' => true]);
        $other = User::factory()->create(['name' => 'Alpha Manager', 'role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Work', 'email' => 'work@example.com', 'username' => 'work@example.com']);
        $mine = $this->mailThread($mailbox, 'mine', ['assigned_user_id' => $manager->id, 'priority' => 'urgent']);
        $otherDue = $this->mailThread($mailbox, 'other', [
            'assigned_user_id' => $other->id, 'status' => 'waiting_customer', 'next_follow_up_at' => now()->subDay(),
        ]);
        $unassigned = $this->mailThread($mailbox, 'unassigned', ['last_message_at' => now()->subDays(2)]);
        $draft = $this->mailThread($mailbox, 'draft', ['status' => 'draft', 'next_follow_up_at' => now()->subDay()]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListCrmMailThreads::class)
            ->assertCanSeeTableRecords([$mine, $otherDue, $unassigned], inOrder: true)
            ->assertCanNotSeeTableRecords([$draft])
            ->assertSee('Пора связаться')
            ->filterTable('assigned_user_id', 'mine')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$otherDue, $unassigned])
            ->filterTable('assigned_user_id', 'unassigned')
            ->assertCanSeeTableRecords([$unassigned])
            ->assertCanNotSeeTableRecords([$mine, $otherDue])
            ->filterTable('assigned_user_id', $other->id)
            ->filterTable('reaction', 'follow_up')
            ->assertCanSeeTableRecords([$otherDue])
            ->call('resetTableFiltersForm')
            ->sortTable('assignedUser.name', 'asc')
            ->assertCanSeeTableRecords([$otherDue, $mine, $unassigned], inOrder: true)
            ->sortTable('assignedUser.name', 'desc')
            ->assertCanSeeTableRecords([$mine, $otherDue, $unassigned], inOrder: true);
    }

    public function test_mail_search_combines_email_and_older_message_content_with_the_selected_folder_and_mailbox(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Work', 'email' => 'work@example.com', 'username' => 'work@example.com', 'is_enabled' => true]);
        $another = CrmMailbox::create(['name' => 'Orders', 'email' => 'orders@example.com', 'username' => 'orders@example.com', 'is_enabled' => true]);
        $match = $this->mailThread($mailbox, 'closed', ['status' => 'closed']);
        $wrongMailbox = $this->mailThread($another, 'elsewhere', ['participant_email' => $match->participant_email]);
        $wrongText = $this->mailThread($mailbox, 'wrong-text', ['participant_email' => $match->participant_email]);

        foreach ([$match, $wrongMailbox] as $thread) {
            $thread->messages()->create([
                'mailbox_id' => $thread->mailbox_id, 'direction' => 'incoming', 'is_read' => true,
                'from_email' => $thread->participant_email,
                'subject' => 'Исходная тема', 'text_body' => 'Артикул CH-217 из запроса', 'received_at' => now()->subDay(),
            ]);
        }

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListCrmMailThreads::class)
            ->assertSee('Email, отправитель, тема, текст, № заказа или КП')
            ->call('selectMailbox', (string) $mailbox->id)
            ->assertSet('mailbox', (string) $mailbox->id)
            ->searchTable($match->participant_email.' CH-217')
            ->assertCanNotSeeTableRecords([$match, $wrongMailbox, $wrongText])
            ->assertSee('Искать во всех письмах')
            ->call('selectMailTab', 'all')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$wrongMailbox, $wrongText])
            ->searchTable('Исходная тема')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$wrongMailbox, $wrongText]);
    }

    private function mailThread(CrmMailbox $mailbox, string $key, array $attributes = []): CrmMailThread
    {
        return $mailbox->threads()->create($attributes + [
            'thread_key' => $key,
            'participant_name' => 'Contact',
            'participant_email' => $key.'@example.com',
            'subject' => 'Последняя тема',
            'status' => 'new',
            'last_message_at' => now(),
        ]);
    }
}
