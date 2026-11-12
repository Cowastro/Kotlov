<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Http\Middleware\CitySubdomain;
use App\Models\CrmMailbox;
use App\Models\User;
use App\Services\CityContactResolver;
use App\Support\AdminSurfaceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmMailLoadRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_counts_use_one_query_and_preserve_mailbox_access_and_trash_counts(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $shared = CrmMailbox::create(['name' => 'Shared', 'email' => 'shared@example.com', 'username' => 'shared@example.com']);
        $private = CrmMailbox::create(['name' => 'Private', 'email' => 'private@example.com', 'username' => 'private@example.com']);
        $private->users()->attach($other);

        foreach (['new' => ['incoming', 'outgoing'], 'closed' => ['incoming', 'outgoing'], 'spam' => ['incoming'], 'draft' => ['draft'], 'trash' => ['incoming']] as $status => $directions) {
            $thread = $shared->threads()->create(['thread_key' => $status, 'participant_email' => 'buyer@example.com', 'status' => $status === 'trash' ? 'new' : $status]);
            foreach ($directions as $direction) {
                $thread->messages()->create(['mailbox_id' => $shared->id, 'direction' => $direction, 'from_email' => 'buyer@example.com', 'is_read' => $direction !== 'incoming']);
            }
            if ($status === 'trash') {
                $thread->delete();
            }
        }
        $hidden = $private->threads()->create(['thread_key' => 'hidden', 'participant_email' => 'private@example.com', 'status' => 'new']);
        $hidden->messages()->create(['mailbox_id' => $private->id, 'direction' => 'incoming', 'from_email' => 'private@example.com', 'is_read' => false]);
        $this->actingAs($manager);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $counts = ListCrmMailThreads::messageBadgeCounts();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(['inbox' => 2, 'unread' => 1, 'incoming' => 1, 'drafts' => 1, 'sent' => 1, 'spam' => 1, 'trash' => 1, 'all' => 6], $counts);
        $this->assertSame($counts, ListCrmMailThreads::messageBadgeCounts($shared->id));
        $this->assertSame(array_fill_keys(array_keys($counts), 0), ListCrmMailThreads::messageBadgeCounts($private->id));
    }

    public function test_admin_and_its_livewire_updates_skip_storefront_city_resolution(): void
    {
        $resolver = \Mockery::mock(CityContactResolver::class);
        $resolver->shouldNotReceive('resolve');
        $middleware = new CitySubdomain($resolver);
        foreach (['https://kotlov.by/admin/crm-mail-threads', 'https://kotlov.by/livewire-f410ccd1/update'] as $url) {
            $request = Request::create($url);
            $request->headers->set('X-Livewire', 'true');
            $request->headers->set('referer', 'https://kotlov.by/admin/crm-mail-threads?tab=unread');
            $this->assertTrue(AdminSurfaceRequest::matches($request));
            $this->assertSame(200, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
        }
        $public = Request::create('https://lida.kotlov.by/livewire-f410ccd1/update');
        $public->headers->set('X-Livewire', 'true');
        $public->headers->set('referer', 'https://lida.kotlov.by/catalog');
        $this->assertFalse(AdminSurfaceRequest::matches($public));
        $this->assertFalse(AdminSurfaceRequest::matches(Request::create('https://lida.kotlov.by/catalog')));
    }
}
