<?php

namespace Tests\Feature;

use App\Support\Catalog\GasMeterSelectionGuide;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GasMeterSelectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_selector_is_listed_in_knowledge_base_and_opens(): void
    {
        $this->get(KnowledgeBase::URL)
            ->assertOk()
            ->assertSeeText('Как подобрать газовый счётчик')
            ->assertSee(GasMeterSelectionGuide::PAGE_URL, false);

        $this->get(GasMeterSelectionGuide::PAGE_URL)
            ->assertOk()
            ->assertSeeText('Как подобрать газовый счётчик без сложных терминов')
            ->assertSeeText('восемь понятных типов счётчиков БелОМО')
            ->assertSeeText('Подобрать счётчик')
            ->assertSeeText('СГД-3Т-1-1-G4')
            ->assertSee('belomo-kata-skat-oboznacheniya#variants', false)
            ->assertSee('data-gm-picker', false)
            ->assertSee('/schetchiki-gaza', false);
    }
}
