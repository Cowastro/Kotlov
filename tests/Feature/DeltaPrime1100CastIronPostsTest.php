<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeltaPrime1100CastIronPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_and_cast_iron_article_are_published_with_faq(): void
    {
        $this->get('/blog/novinka-ekokamin-delta-praim-1100-chugunnaya-topochnaya-kamera')->assertOk()
            ->assertSee('Новинка ЭкоКамин: топка ДЕЛЬТА ПРАЙМ 1100 с чугунной топочной камерой')
            ->assertSee('delta-prime-1100-chugun-novinka.webp', false)
            ->assertSee('/blog/chugunnaya-futerovka-kamina-preimushchestva', false)
            ->assertSee('/topki/topka-delta-praim-1100-tri-stekla-futerovka-cugun-siber-vstroennyi-ramka-dekorativnaia', false);

        $this->get('/blog/chugunnaya-futerovka-kamina-preimushchestva')->assertOk()
            ->assertSee('Чугунная футеровка камина: преимущества, отличие от шамота и вермикулита')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Чем чугунная футеровка лучше шамотной?')
            ->assertSee('preimushchestva-chugunnoy-futerovki.webp', false);

        $this->assertFileExists(public_path('img/blog/ecokamin-prime/delta-prime-1100-chugun-novinka.webp'));
        $this->assertFileExists(public_path('img/blog/chugunnaya-futerovka/preimushchestva-chugunnoy-futerovki.webp'));
    }
}
