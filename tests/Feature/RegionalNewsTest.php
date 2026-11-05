<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\BlogPost;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalNewsTest extends TestCase
{
    use RefreshDatabase;

    private City $lida;
    private City $borisov;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.base_domain' => 'kotlov.by']);
        City::create(['slug' => 'minsk', 'name' => 'Минск', 'name_in' => 'в Минске', 'name_title' => 'Минске', 'is_active' => true, 'storefront_enabled' => true]);
        $this->lida = $this->shop('lida', 'Лида', 'Лиде');
        $this->borisov = $this->shop('borisov', 'Борисов', 'Борисове');
    }

    public function test_city_feeds_and_home_show_only_their_published_local_news(): void
    {
        $this->article('lida-local', $this->lida);
        $this->article('borisov-local', $this->borisov);
        $this->article('general-article');
        $this->article('lida-draft', $this->lida, ['is_published' => false]);
        $this->article('lida-future', $this->lida, ['published_at' => now()->addDay()]);

        foreach (['/news', '/'] as $path) {
            $this->get('https://lida.kotlov.by'.$path)->assertOk()
                ->assertSee('Материал lida-local')->assertDontSee('Материал borisov-local')
                ->assertDontSee('Материал lida-draft')->assertDontSee('Материал lida-future');
        }
        $this->get('https://lida.kotlov.by/news')->assertDontSee('Материал general-article');
        $this->get('https://kotlov.by/regions/news')->assertOk()
            ->assertSee('https://lida.kotlov.by/news/lida-local', false)
            ->assertSee('https://borisov.kotlov.by/news/borisov-local', false)
            ->assertDontSee('Материал general-article');
    }

    public function test_direct_store_contact_pilot_is_lida_only_and_requires_a_public_store(): void
    {
        $branch = PartnerBranch::where('city_id', $this->lida->id)->firstOrFail();
        $branch->update(['telegram_username' => 'kotlovlida', 'viber_phone' => '+375299283766', 'instagram_username' => 'kotlovlida.by']);
        $this->get('https://lida.kotlov.by/')->assertOk()
            ->assertSee('aria-label="Быстрая связь с магазином в Лиде"', false)
            ->assertSee('https://t.me/kotlovlida', false)
            ->assertSee('viber://chat?number=%2B375299283766', false)
            ->assertSee('https://www.instagram.com/kotlovlida.by/', false);
        $this->get('https://borisov.kotlov.by/')->assertOk()
            ->assertDontSee('aria-label="Быстрая связь с магазином в Лиде"', false);
        $branch->update(['is_active' => false]);
        $this->assertStringNotContainsString('regional-quick-contact', view('partials.regional-quick-contact', ['currentCity' => $this->lida])->render());
    }

    public function test_article_has_one_city_canonical_and_cannot_be_read_under_another_city(): void
    {
        $post = $this->article('local-story', $this->lida, ['updated_at' => now()->subDays(2)]);
        $modifiedAt = $post->updated_at->toDateTimeString();
        $this->get('https://lida.kotlov.by/news/local-story')->assertOk()
            ->assertSee('<link rel="canonical" href="https://lida.kotlov.by/news/local-story">', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('"name":"Лида"', false);
        $this->assertSame($modifiedAt, $post->fresh()->updated_at->toDateTimeString());
        $this->get('https://borisov.kotlov.by/news/local-story')->assertNotFound();
        $this->get('https://kotlov.by/blog/local-story')->assertRedirect('https://lida.kotlov.by/news/local-story');
        $this->get('https://kotlov.by/news/local-story')->assertNotFound();
        $this->get('https://kotlov.by/blog')->assertOk()->assertDontSee('Материал local-story');
    }

    public function test_sitemap_and_cached_home_update_when_local_article_is_unpublished(): void
    {
        $post = $this->article('local-change', $this->lida);
        $this->article('other-city', $this->borisov);
        $this->get('https://lida.kotlov.by/sitemap.xml')->assertOk()
            ->assertSee('https://lida.kotlov.by/news/local-change', false)
            ->assertDontSee('other-city');
        $this->get('https://kotlov.by/sitemap.xml')->assertOk()
            ->assertDontSee('/blog/local-change')->assertSee('https://kotlov.by/regions/news', false);
        $this->get('https://lida.kotlov.by/')->assertSee('Материал local-change');

        $post->update(['is_published' => false]);
        $this->get('https://lida.kotlov.by/news/local-change')->assertNotFound();
        $this->get('https://lida.kotlov.by/sitemap.xml')->assertDontSee('local-change');
        $this->get('https://lida.kotlov.by/')->assertDontSee('Материал local-change');
    }

    public function test_disabling_partner_hides_their_news_from_the_aggregate(): void
    {
        $this->article('closed-shop', $this->lida);
        $this->lida->partnerBranches()->first()->client->update(['status' => 'suspended']);
        $this->get('https://kotlov.by/regions/news')->assertOk()->assertDontSee('Материал closed-shop');
        $this->get('https://lida.kotlov.by/news/closed-shop')->assertNotFound();
    }

    private function shop(string $slug, string $name, string $in): City
    {
        $city = City::create(['slug' => $slug, 'name' => $name, 'name_in' => 'в '.$in, 'name_title' => $in, 'is_active' => true, 'storefront_enabled' => true]);
        $user = User::factory()->create();
        $client = B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Магазин '.$name]);
        PartnerBranch::create(['city_id' => $city->id, 'b2b_client_id' => $client->id, 'name' => 'Магазин '.$name, 'address' => 'Адрес '.$name, 'phones' => ['+375291112233'], 'working_hours' => 'Вт–Сб 09:00–18:00', 'is_active' => true]);
        return $city;
    }

    private function article(string $slug, ?City $city = null, array $extra = []): BlogPost
    {
        return BlogPost::create(array_merge(['slug' => $slug, 'city_id' => $city?->id, 'title' => 'Материал '.$slug, 'excerpt' => 'Анонс '.$slug, 'content' => '<p>Текст новости.</p>', 'is_published' => true, 'published_at' => now()->subMinute()], $extra));
    }
}
