<?php

namespace Tests\Unit;

use App\Services\Suppliers\HaierTechnicalCatalog;
use App\Services\Suppliers\TermTeploFeed;
use App\Services\Suppliers\TermTeploModel;
use RuntimeException;
use Tests\TestCase;

class TermTeploFeedTest extends TestCase
{
    public function test_complete_models_disambiguate_the_krats_price_typo_and_keep_accessories_and_variants_separate(): void
    {
        $this->assertSame('KEB-12VW-ECO', TermTeploModel::article('KEB-24VW', 'Котел электрический KRATS ECO 12кВт 1к настенный 220/380В Wi-Fi (Насос KRATS)'));
        $this->assertSame('KEB-24VW', TermTeploModel::article('KEB-24VW', 'Котел электрический KRATS 24кВт 1к настенный 380В Wi-Fi (Насос Grundfos)'));
        $this->assertNull(TermTeploModel::key('Клапан для KRATS ECO 12кВт'));
        $this->assertNotSame(TermTeploModel::key('KRATS ECO 12кВт'), TermTeploModel::key('KRATS 12кВт'));
        $this->assertSame(TermTeploModel::key('Haier ES80V-A2'), TermTeploModel::key('ES80V-А2 Водонагреватель Хайер'));
        $this->assertNull(TermTeploModel::key('Электрический водонагреватель Haier ES10V-Q2(R) / Q1(R)'));
        $this->assertSame(TermTeploModel::family('Haier Proline 28'), TermTeploModel::family('ProLine S 2.28 TiW', 'Haier'));
        $this->assertNotSame(TermTeploModel::key('Haier Proline 28'), TermTeploModel::key('ProLine S 2.28 TiW', 'Haier'));
    }

    public function test_rotating_stock_file_is_selected_by_report_date(): void
    {
        $folder = '<span id="entry-price"><span class="flip-entry-title">Прайс ООО ТермТеплоСистем 12.10.24</span></span>
            <span id="entry-new"><span class="flip-entry-title">остатки 02.01.25.xlsx</span></span>
            <span id="entry-old"><span class="flip-entry-title">остатки 31.12.24.xlsx</span></span>';
        $feed = new TermTeploFeed;
        $this->assertSame(['id' => 'new', 'date' => '2025-01-02'], $feed->sources($folder)['stock']);
        $this->expectException(RuntimeException::class);
        $feed->sources($folder.'<span id="entry-copy"><span class="flip-entry-title">остатки 02.01.25.xlsx</span></span>');
    }

    public function test_specs_are_grounded_in_exact_article_and_section(): void
    {
        $html = '<div class="pd__services-text">Артикул</div><div class="pd__services-text">ABC123RU</div>
            <div class="characteristic-list__section"><span class="characteristic-list__caption">Габариты упаковки</span>
            <div class="characteristic-list__item"><span class="characteristic-list__key">Высота, мм</span>
            <span class="characteristic-list__val">800</span></div></div>';
        $catalog = new HaierTechnicalCatalog;
        $this->assertSame([], $catalog->specifications('ABC123', $html));
        $this->assertSame(['Габариты упаковки · Высота, мм' => '800'], $catalog->specifications('ABC123RU', $html));
    }

    public function test_preposted_statement_becomes_current_at_midnight_in_minsk(): void
    {
        $folder = '<div id="entry-price"><b class="flip-entry-title">Прайс ООО ТермТеплоСистем 12.10.26</b></div>
            <div id="entry-old"><b class="flip-entry-title">остатки 19.11.26.xlsx</b></div>
            <div id="entry-new"><b class="flip-entry-title">остатки 23.11.26.xlsx</b></div>';
        $feed = new TermTeploFeed;
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-22 23:59:00', 'Europe/Minsk'));
        $this->assertSame('old', $feed->sources($folder)['stock']['id']);
        $this->travel(1)->minutes();
        $this->assertSame('new', $feed->sources($folder)['stock']['id']);
    }
}
