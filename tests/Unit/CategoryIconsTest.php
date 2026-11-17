<?php

namespace Tests\Unit;

use App\Support\CategoryIcons;
use PHPUnit\Framework\TestCase;

class CategoryIconsTest extends TestCase
{
    /** Sibling tiles shown together on one catalog page (slugs from the live catalog). */
    private const PAGES = [
        'santehnika' => ['smesiteli', 'dushevye-sistemy', 'gibkaya-podvodka-dlya-vody', 'zhestkaya-podvodka-dlya-vody', 'shlangi-i-soediniteli',
            'sifony-i-trapy', 'installyatsii-i-armatura-bachkov', 'santekhnicheskaya-posuda', 'polotentsesushiteli', 'komplektuyushchie-dlya-santekhniki'],
        'klimat' => ['kondicionery', 'mobilnye-kondicionery', 'obogrevateli', 'naruzhnyy-obogrev', 'uvlazhniteli-vozdukha', 'mojki-vozdukha', 'ventilyatory', 'gibkie-vozduhovody'],
        'radiatory' => ['stalnye-radiatory', 'alyuminievye-radiatory', 'bimetallicheskie-radiatory', 'chugunnye-radiatory', 'vodyanye-konvektory', 'radiatornaya-armatura'],
        'truby-i-fitingi' => ['polipropilenovye-truby', 'truby-iz-sshitogo-polietilena', 'metalloplastikovye-truby', 'polietilenovye-truby', 'truby-npvh-napornye',
            'metallicheskie-truby', 'gofrirovannye-truby-iz-nerzhaveyushchej-stali', 'gofrirovanye-truby', 'komplektuyushchie-dlya-gofrirovannoj-truby', 'press-fitingi',
            'rezbovye-fitingi', 'fitingi-dlya-polipropilenovykh-trub', 'fitingi-dlya-trub-pex', 'fitingi-dlya-pe-trub', 'fitingi-dlya-metalloplastikovykh-trub',
            'fitingi-dlya-mednyh-trub', 'stalnye-detali-truboprovodov', 'flantsy', 'kompressionnye-fitingi', 'fitingi-dlya-gofrirovannoj-truby',
            'vnutrennyaya-kanalizatsiya', 'naruzhnaya-kanalizatsiya', 'chugunnye-truby-i-fasonnye-chasti', 'lyuki-i-dozhdepriemniki', 'teploizolyatsiya-dlya-trub', 'krepleniya-dlya-trub'],
        'krany-i-zapornaya-armatura' => ['sharovye-krany', 'vodorazbornye-krany', 'ventili-zapornye', 'zadvizhki', 'zatvory-diskovye', 'obratnye-klapany', 'filtry',
            'predokhranitelnaya-i-reguliruyushchaya-armatura', 'smesitelnye-klapany-i-uzly', 'smesitelnaya-armatura', 'manometry-i-termometry', 'schetchiki-vody-i-tepla'],
    ];

    public function test_every_tile_on_a_page_has_its_own_drawing(): void
    {
        foreach (self::PAGES as $page => $slugs) {
            $paths = [];
            foreach ($slugs as $slug) {
                $icon = CategoryIcons::for($slug);
                $this->assertNotNull($icon, "{$page}: no drawing for {$slug}");
                $this->assertMatchesRegularExpression('/^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/', $icon[1], "{$slug}: path has stray characters");
                $paths[$slug] = $icon[1];
            }
            $this->assertSame(count($paths), count(array_unique($paths)), "{$page}: two tiles share a drawing");
        }
    }

    public function test_unknown_slug_falls_back_to_the_font_icon(): void
    {
        $this->assertNull(CategoryIcons::for('no-such-section'));
    }
}
