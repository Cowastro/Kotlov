<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EcokaminPrimeLaunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prime_banner_leads_the_home_slider_and_the_article_is_published(): void
    {
        Cache::flush();
        $this->assertTrue(DB::table('banners')->where('image', 'banners/ecokamin-prime-launch')->where('is_active', true)->exists());

        $this->get('/')->assertOk()
            ->assertSee('prime-cover__art', false)
            ->assertSee('prime-cover__title">ПРАЙМ', false)
            ->assertSee('/blog/ekokamin-praim-novye-topki-vega-i-delta', false);

        $this->get('/blog/ekokamin-praim-novye-topki-vega-i-delta')->assertOk()
            ->assertSee('ДЕЛЬТА ПРАЙМ 1100')
            ->assertSee('/topki/topka-delta-praim-1100-tri-stekla-futerovka-cugun-siber-vstroennyi-ramka-dekorativnaia', false);
    }
}
