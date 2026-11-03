<?php

namespace Tests\Feature;

use App\Models\PartnerProgramLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnersBusinessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_form_and_type_specific_database_levels(): void
    {
        PartnerProgramLevel::query()->delete();
        $level = PartnerProgramLevel::create([
            'name' => 'Тестовый уровень',
            'rank' => 1,
            'rules' => [['metric' => 'revenue', 'target' => 15000, 'months' => 6]],
            'benefits' => [
                'wholesale_prices' => 'Оптовые условия',
                'directory_priority' => 'Приоритет для монтажников',
                'site_leads' => 'Заявки по региону',
            ],
        ]);

        $this->get('/partners')->assertOk()->assertSee('id="company-connect"', false)
            ->assertSee('action="'.route('register').'"', false)
            ->assertSeeText('Тестовый уровень')->assertSeeText('15 000')
            ->assertSeeText('Оптовые условия')->assertDontSeeText('Приоритет для монтажников')
            ->assertDontSeeText('Заявки по региону');

        $this->get('/partners?type=installer')->assertOk()
            ->assertSeeText('Приоритет для монтажников')->assertSeeText('Заявки по региону');

        $level->update(['is_active' => false]);
        $this->get('/partners')->assertOk()->assertDontSee('id="partner-levels"', false);

        $level->delete();
        $this->get('/partners')->assertOk()->assertDontSee('id="partner-levels"', false);
    }
}
