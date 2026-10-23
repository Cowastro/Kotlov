<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentInformationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_information_page_is_available_and_self_contained(): void
    {
        $this->get(route('installment.info'))
            ->assertOk()
            ->assertSeeText('Покупка в рассрочку до 6 месяцев')
            ->assertSeeText('Как оформить рассрочку')
            ->assertSeeText('Важные условия')
            ->assertSeeText('Скидки не суммируются')
            ->assertSeeText('В ролике может быть указан прежний срок программы')
            ->assertSee('https://www.youtube-nocookie.com/embed/nw2v3z5BZms?rel=0', false)
            ->assertDontSee('sbg.by', false);
    }
}
