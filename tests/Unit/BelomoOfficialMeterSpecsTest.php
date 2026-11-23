<?php

namespace Tests\Unit;

use App\Support\Catalog\BelomoOfficialAssets;
use App\Support\Catalog\BelomoOfficialMeterSpecs;
use App\Support\Catalog\BelomoSelectorLineup;
use Tests\TestCase;

class BelomoOfficialMeterSpecsTest extends TestCase
{
    public function test_all_79_executions_have_local_images_documents_and_unambiguous_sizes(): void
    {
        $profiles = BelomoOfficialMeterSpecs::all();
        $this->assertCount(79, $profiles);
        foreach ($profiles as $article => $p) {
            $this->assertSame($article, $p['specs']['Артикул производителя']);
            $this->assertStringNotContainsString('L=', $p['name']);
            $this->assertStringNotContainsString('https://belomo.by', $p['content']);
            $this->assertNotEmpty($p['images']);
            $this->assertNotEmpty($p['documents']);
            foreach ($p['images'] as $image) {
                $this->assertStringStartsWith('/img/products/belomo-official/', $image);
            }
            foreach ($p['documents'] as $doc) {
                $this->assertStringStartsWith('/docs/belomo/', $doc['url']);
            }
        }
        BelomoOfficialAssets::verifyFiles();
    }

    public function test_factory_appendix_confirms_8181_24_and_corrects_the_two_non_pulse_executions(): void
    {
        $profiles = BelomoOfficialMeterSpecs::all();
        $g4 = $profiles['8181.00.00.000-24'];
        $this->assertSame(250, $g4['distance']);
        $this->assertSame('left', $g4['direction']);
        $this->assertSame('310 × 185 × 245 мм', $g4['specs']['Габариты корпуса (длина × ширина × высота)']);
        $this->assertSame('6 м³/ч', $g4['specs']['Максимальный расход газа']);
        foreach (['08', '09'] as $suffix) {
            $p = $profiles['8181.00.00.000-'.$suffix];
            $this->assertSame('Нет', $p['specs']['Импульсный выход']);
            $this->assertSame('Без присоединительных элементов', $p['specs']['Комплектация']);
            $this->assertStringNotContainsString('И-', $p['model']);
        }
        $models = collect(BelomoSelectorLineup::models());
        $this->assertSame(250, $models->firstWhere('article_label', '8181.00.00.000-24')['length']);
    }

    public function test_sgd1_uses_current_type_description_not_conflicting_website_summary(): void
    {
        $p = BelomoOfficialMeterSpecs::all()['8072.00.00.000-21'];
        $this->assertSame(110, $p['distance']);
        $this->assertSame('160 × 135 × 230 мм', $p['specs']['Габариты корпуса (длина × ширина × высота)']);
        $this->assertSame('30 кПа', $p['specs']['Максимальное рабочее давление']);
        $this->assertStringContainsString('12.09.2024', $p['specs']['Межповерочный интервал']);
        $this->assertStringContainsString('8 лет', $p['specs']['Межповерочный интервал']);
    }

    public function test_other_series_prefer_the_certificate_over_inaccurate_web_summaries_and_use_matching_photos(): void
    {
        $all = BelomoOfficialMeterSpecs::all();
        $this->assertSame('97 × 40 × 210 мм', $all['8345.00.00.000']['specs']['Габариты корпуса (длина × ширина × высота)']);
        $this->assertSame('1,5 кг', $all['8349.00.00.000']['specs']['Масса, не более']);
        $this->assertSame('35 кПа', $all['8336.00.00.000-58']['specs']['Максимальное рабочее давление']);
        $this->assertSame(['/img/products/belomo-official/sgd4-58.png'], $all['8336.00.00.000-58']['images']);
        $this->assertSame(['/img/products/belomo-official/sgd4-2.png'], $all['8336.00.00.000-06']['images']);
    }
}
