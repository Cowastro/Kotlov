<?php

namespace Tests\Feature;

use App\Http\Controllers\ReleaseNotesController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_link_opens_the_release_without_login_for_48_hours(): void
    {
        $link = ReleaseNotesController::temporaryLink('2026-10-26');

        $this->get($link)
            ->assertOk()
            ->assertSee('Быстрый заказ')
            ->assertSee('noindex', false)
            ->assertDontSee('Все релизы');

        $this->travel(ReleaseNotesController::LINK_HOURS + 1)->hours();
        $this->get($link)->assertForbidden();
    }

    public function test_release_without_signature_is_closed_to_guests_and_customers(): void
    {
        $this->get('/releases/2026-10-26')->assertForbidden();

        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($customer)->get('/releases/2026-10-26')->assertForbidden();
        $this->actingAs($customer)->get('/admin/releases')->assertForbidden();
    }

    public function test_staff_see_the_list_with_a_fresh_link_and_open_releases_directly(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager)->get('/admin/releases')
            ->assertOk()
            ->assertSee('Скопировать ссылку')
            ->assertSee('signature=', false);

        $this->actingAs($manager)->get('/releases/2026-10-26')->assertOk()->assertSee('Все релизы');
        $this->actingAs($manager)->get('/releases/2026-01-01')->assertNotFound();
    }

    public function test_guest_is_sent_to_the_admin_login_for_the_list(): void
    {
        $this->get('/admin/releases')->assertRedirect(url('/admin/login'));
    }
}
