<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_mobile_header_offers_logout_instead_of_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $html = view('partials.amerce-header', [
            'navCategories' => collect(),
            'navCategoryUrls' => [],
            'navBrands' => collect(),
        ])->render();

        $this->assertStringContainsString('aria-label="Выйти из аккаунта"', $html);
        $this->assertStringContainsString('action="'.route('logout').'"', $html);
        $this->assertStringNotContainsString('href="#sign"', $html);
    }

    public function test_guest_mobile_header_still_offers_login(): void
    {
        $html = view('partials.amerce-header', [
            'navCategories' => collect(),
            'navCategoryUrls' => [],
            'navBrands' => collect(),
        ])->render();

        $this->assertStringContainsString('aria-label="Войти в аккаунт"', $html);
        $this->assertStringContainsString('href="#sign"', $html);
        $this->assertStringNotContainsString('aria-label="Выйти из аккаунта"', $html);
    }

    public function test_authenticated_pages_do_not_render_guest_account_modals(): void
    {
        $this->actingAs(User::factory()->create());

        $html = view('partials.modals')->render();

        $this->assertStringNotContainsString('id="sign"', $html);
        $this->assertStringNotContainsString('id="register"', $html);
        $this->assertStringNotContainsString('id="modalForgot"', $html);
    }
}
