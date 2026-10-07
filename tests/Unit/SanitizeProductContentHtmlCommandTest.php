<?php

namespace Tests\Unit;

use App\Console\Commands\SanitizeProductContentHtmlCommand;
use App\Services\ProductSourceEnricher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SanitizeProductContentHtmlCommandTest extends TestCase
{
    public function test_legacy_line_removal_is_normalized_in_the_same_pass(): void
    {
        $command = new SanitizeProductContentHtmlCommand();
        $removeLegacyText = new ReflectionMethod($command, 'removeLegacyBuyTemplateText');
        $enricher = new ProductSourceEnricher();

        $withoutLegacyText = $removeLegacyText->invoke(
            $command,
            "Надёжный электрический конвектор для основного и дополнительного отопления.\nВы можете купить данный товар в Минске с доставкой по Беларуси."
        );
        $normalized = $enricher->sanitizeDescriptionHtml($withoutLegacyText);

        $this->assertSame(
            '<p>Надёжный электрический конвектор для основного и дополнительного отопления.</p>',
            $normalized
        );
        $this->assertSame($normalized, $enricher->sanitizeDescriptionHtml($normalized));
    }
}
