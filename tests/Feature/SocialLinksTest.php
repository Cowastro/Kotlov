<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_page_contains_both_current_instagram_channels(): void
    {
        $response = $this->get(route('contacts'));

        $response->assertOk();
        $response->assertSee('https://www.instagram.com/pechkin_shop', false);
        $response->assertSee('https://www.instagram.com/hotta.hp', false);
        $response->assertSee('Instagram — Pechkin Shop', false);
        $response->assertSee('Instagram — HOTTA', false);
        $response->assertSee('https://www.facebook.com/share/1CyJNQ9iuv/', false);
        $response->assertSee('Facebook — Pechkin Shop', false);
        $response->assertDontSee('instagram.com/kotlov.by', false);
        $response->assertDontSee('facebook.com/kotlov.by', false);
    }
}
