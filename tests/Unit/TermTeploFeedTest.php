<?php

namespace Tests\Unit;

use App\Services\Suppliers\HaierTechnicalCatalog;
use App\Services\Suppliers\TermTeploFeed;
use RuntimeException;
use Tests\TestCase;

class TermTeploFeedTest extends TestCase
{
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
}
