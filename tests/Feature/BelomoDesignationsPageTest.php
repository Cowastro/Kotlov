<?php

namespace Tests\Feature;

use App\Support\Catalog\BelomoGasMeterDesignations;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelomoDesignationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_match_manufacturer_tables(): void
    {
        $variants = collect(BelomoGasMeterDesignations::variants())->keyBy('code');

        $this->assertCount(48, $variants->where('model', 'kata'));
        $this->assertCount(32, $variants->where('model', 'skat'));

        $this->assertSame('Счетчик газа ультразвуковой КАТА – G4 – 1', $variants['8348.00.00.000']['name']);
        $this->assertSame('Счетчик газа ультразвуковой КАТА – G4 RK – 3 (NB-IoT)', $variants['8348.00.00.000-11']['name']);
        $this->assertSame('Счетчик газа ультразвуковой КАТА – G4 B – 1 с адаптером для подключения по протоколу Modbus', $variants['8348.00.00.000-15']['name']);
        $this->assertSame('Счетчик газа ультразвуковой КАТА – G6 B – 3 с адаптером для подключения ПК', $variants['8348.00.00.000-34']['name']);
        $this->assertSame('Счетчик газа ультразвуковой КАТА – G4 RK – 2 (GSM)', $variants['8348.00.00.000-43']['name']);
        $this->assertSame('Счетчик газа ультразвуковой КАТА – G6 RK – 3 (GSM)', $variants['8348.00.00.000-55']['name']);

        $this->assertSame('Счетчик газа ультразвуковой СКАТ – G6 P', $variants['8349.00.00.000-04']['name']);
        $this->assertSame('Счетчик газа ультразвуковой СКАТ – G10 RKP (NB-IoT)', $variants['8349.00.00.000-17']['name']);
        $this->assertSame('Счетчик газа ультразвуковой СКАТ – G6 BP с адаптером для подключения по протоколу Modbus', $variants['8349.00.00.000-23']['name']);
        $this->assertSame('Счетчик газа ультразвуковой СКАТ – G10 B с адаптером для подключения ПК', $variants['8349.00.00.000-24']['name']);
        $this->assertSame('Счетчик газа ультразвуковой СКАТ – G10 RKP (GSM)', $variants['8349.00.00.000-37']['name']);
    }

    public function test_knowledge_base_shows_designations_and_downloads(): void
    {
        $this->get(KnowledgeBase::URL)
            ->assertOk()
            ->assertSeeText('База знаний')
            ->assertSeeText('Техническая информация')
            ->assertSee('article-blog', false)
            ->assertSee(BelomoGasMeterDesignations::PAGE_URL, false);

        $this->get(KnowledgeBase::URL . '?q=' . urlencode('скат nb-iot'))
            ->assertOk()
            ->assertSee(BelomoGasMeterDesignations::PAGE_URL, false);

        $this->get(KnowledgeBase::URL . '?q=' . urlencode('пеллетный котел'))
            ->assertOk()
            ->assertSeeText('Ничего не найдено');

        $this->get(KnowledgeBase::URL . '?tag=' . urlencode('БелОМО'))
            ->assertOk()
            ->assertSee(BelomoGasMeterDesignations::PAGE_URL, false);

        $this->get(BelomoGasMeterDesignations::PAGE_URL)
            ->assertOk()
            ->assertSeeText('Обозначения счетчиков газа БелОМО КАТА и СКАТ')
            ->assertSeeText('ТУ BY 100185185.277-2023')
            ->assertSeeText('8349.00.00.000-37')
            ->assertSee(BelomoGasMeterDesignations::PDF_URL, false)
            ->assertSee(BelomoGasMeterDesignations::CSV_URL, false);

        $this->get('/')
            ->assertSee('href="' . KnowledgeBase::URL . '"', false);

        $this->get('/partners')
            ->assertOk()
            ->assertDontSee(BelomoGasMeterDesignations::PAGE_URL, false);

        $this->assertFileExists(public_path(ltrim(BelomoGasMeterDesignations::PDF_URL, '/')));
    }

    public function test_old_partner_urls_redirect_to_knowledge_base(): void
    {
        $this->get(BelomoGasMeterDesignations::LEGACY_PAGE_URL)
            ->assertStatus(301)
            ->assertRedirect(BelomoGasMeterDesignations::PAGE_URL);

        $this->get(BelomoGasMeterDesignations::LEGACY_PAGE_URL . '.csv')
            ->assertStatus(301)
            ->assertRedirect(BelomoGasMeterDesignations::CSV_URL);
    }

    public function test_csv_download_contains_all_variants(): void
    {
        $response = $this->get(BelomoGasMeterDesignations::CSV_URL);

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(81, count(array_filter(explode("\n", trim($csv)))));
        $this->assertStringContainsString('8348.00.00.000-43;G4;RK;"2 (G1)";GSM', $csv);
    }
}
