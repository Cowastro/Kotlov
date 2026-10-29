<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicPhoneNumberTest extends TestCase
{
    public function test_retail_templates_and_legal_details_use_the_current_kotlov_phone(): void
    {
        $publicSources = collect(File::allFiles(resource_path('views')))
            ->reject(fn ($file): bool => str_contains(
                str_replace('\\', '/', $file->getPathname()),
                '/views/pages/b2b/',
            ))
            ->map(fn ($file): string => File::get($file->getPathname()))
            ->push(File::get(config_path('legal.php')))
            ->implode("\n");

        $this->assertStringNotContainsString('+375 (29) 354-40-41', $publicSources);
        $this->assertStringNotContainsString('+375293544041', $publicSources);
        $this->assertStringContainsString('+375 (29) 377-70-92', $publicSources);
        $this->assertStringContainsString('+375293777092', $publicSources);

        $this->assertSame('+375 (29) 377-70-92', config('legal.seller.phones.0'));
        $this->assertSame('+375 (29) 377-70-92', config('legal.commercial_documents.phones.0'));
    }

    public function test_wholesale_phone_is_reserved_for_b2b_and_labeled_in_telephony(): void
    {
        $this->assertSame('+375 (29) 354-40-41', config('shop.b2b_contact.phone'));
        $this->assertSame('+375293544041', config('shop.b2b_contact.phone_href'));
        $this->assertSame('Оптовый отдел', config('telephony.company_lines.375293544041'));
    }
}
