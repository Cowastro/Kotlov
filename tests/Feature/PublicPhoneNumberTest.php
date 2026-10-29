<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicPhoneNumberTest extends TestCase
{
    public function test_public_templates_and_legal_details_use_the_current_kotlov_phone(): void
    {
        $publicSources = collect(File::allFiles(resource_path('views')))
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
}
