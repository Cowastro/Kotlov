<?php

namespace Tests\Unit;

use App\Rules\NoHtmlOrLinks;
use PHPUnit\Framework\TestCase;

class NoHtmlOrLinksTest extends TestCase
{
    public function test_it_rejects_html_links_and_bare_domains(): void
    {
        $samples = [
            '<a href="https://krakenwebes.net/">Kraken</a>',
            'Read more at https://example.net/path',
            'Visit www.example.com please',
            'Promo on krakenwebes.net today',
            '[url=https://example.com]link[/url]',
        ];

        foreach ($samples as $sample) {
            $this->assertFalse($this->passesRule($sample), $sample);
        }
    }

    public function test_it_allows_plain_review_text(): void
    {
        $this->assertTrue($this->passesRule('Good boiler, delivery was fast and the manager helped with selection.'));
    }

    private function passesRule(string $value): bool
    {
        $failed = false;

        (new NoHtmlOrLinks())->validate('text', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
