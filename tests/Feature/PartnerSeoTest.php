<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_pages_have_canonical_metadata_and_breadcrumbs(): void
    {
        foreach ([
            ['/partners', 'Для бизнеса — отопление и водоснабжение | KOTLOV.BY', 'Подключите компанию к KOTLOV. Оптовый каталог оборудования для отопления и водоснабжения, проверенные склады и партнёрские условия для монтажников и бизнеса.'],
            ['/become-installer', 'Стать монтажником KOTLOV — заявки на монтаж отопления в Беларуси', 'Станьте монтажником KOTLOV: получайте заявки на монтаж котлов, дымоходов, каминов, тепловых насосов и систем отопления в Беларуси.'],
        ] as [$path, $title, $description]) {
            $response = $this->get($path.'?utm_source=example')->assertOk()
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertSee('<meta name="description" content="'.$description.'">', false)
                ->assertSee('<link rel="canonical" href="https://kotlov.by'.$path.'">', false)
                ->assertSee('"@type":"BreadcrumbList"', false)
                ->assertSee('"item":"https://kotlov.by'.$path.'"', false);

            $this->assertSame(1, preg_match_all('/<h1\b/i', $response->getContent()));
        }
    }

    public function test_installation_pages_link_to_both_partner_paths(): void
    {
        foreach (['/montazh-teplovyh-nasosov', '/montazh-kaminov'] as $path) {
            $this->get($path)->assertOk()->assertSeeText('Вы монтажная организация?')
                ->assertSee('href="/become-installer#apply"', false)
                ->assertSee('href="/partners">Для бизнеса', false);
        }
    }
}
