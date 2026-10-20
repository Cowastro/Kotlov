<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcokaminPortfolioManifestTest extends TestCase
{
    #[Test]
    public function the_premium_portfolio_manifest_references_optimized_local_images(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(resource_path('data/ecokamin-portfolio.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertGreaterThanOrEqual(60, count($manifest));

        $paths = [];
        foreach ($manifest as $sourceId => $entry) {
            $this->assertMatchesRegularExpression('/^\d+$/', (string) $sourceId);
            $this->assertSame((string) $sourceId, (string) $entry['source_id']);
            $this->assertNotSame('', trim((string) $entry['name']));
            $this->assertStringStartsWith('https://www.ecokamin.ru/catalog/', (string) $entry['catalog_url']);
            $this->assertGreaterThanOrEqual(1, (int) $entry['project_count']);
            $this->assertNotEmpty($entry['images']);
            $this->assertLessThanOrEqual(5, count($entry['images']));

            foreach ($entry['images'] as $image) {
                $this->assertStringStartsWith('img/products/ecokamin/portfolio-full/', $image);
                $this->assertFileExists(public_path($image));
                $this->assertNotContains($image, $paths);
                $this->assertStringNotContainsString('-19300-', $image);
                $this->assertStringNotContainsString('-19301-', $image);
                $this->assertStringNotContainsString('5519-18565-', $image);
                $this->assertNotSame('img/products/ecokamin/portfolio-full/5513-18687-2.webp', $image);

                $size = getimagesize(public_path($image));
                $this->assertIsArray($size);
                $this->assertLessThanOrEqual(1400, max($size[0], $size[1]));
                $paths[] = $image;
            }
        }

        $this->assertGreaterThanOrEqual(175, count($paths));

        $hero = public_path('img/products/ecokamin/portfolio-full/ecokamin-firebox-real-hero-retouched.webp');
        $this->assertFileExists($hero);
        $heroSize = getimagesize($hero);
        $this->assertIsArray($heroSize);
        $this->assertLessThanOrEqual(1600, max($heroSize[0], $heroSize[1]));
    }
}
