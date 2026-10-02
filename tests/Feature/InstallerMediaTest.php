<?php

namespace Tests\Feature;

use App\Filament\Support\InstallerMediaUpload;
use App\Support\InstallerMedia;
use Filament\Forms\Components\FileUpload;
use Tests\TestCase;

class InstallerMediaTest extends TestCase
{
    public function test_it_recognizes_legacy_public_installer_media_paths(): void
    {
        $this->assertTrue(InstallerMedia::isLegacyPublicPath('img/installers/example.jpg'));
        $this->assertTrue(InstallerMedia::isLegacyPublicPath('/video/installers/example.mp4'));
        $this->assertFalse(InstallerMedia::isLegacyPublicPath('installers/photos/example.jpg'));
    }

    public function test_it_builds_file_upload_metadata_for_legacy_public_media(): void
    {
        $metadata = InstallerMedia::fileUploadMetadata(
            'img/installers/ns-trade/03-multicircuit-boiler-room.webp',
        );

        $this->assertSame('03-multicircuit-boiler-room.webp', $metadata['name']);
        $this->assertGreaterThan(0, $metadata['size']);
        $this->assertSame('image/webp', $metadata['type']);
        $this->assertSame(
            asset('img/installers/ns-trade/03-multicircuit-boiler-room.webp'),
            $metadata['url'],
        );
    }

    public function test_installer_uploads_use_public_disk_without_dropping_legacy_paths(): void
    {
        $upload = InstallerMediaUpload::configure(FileUpload::make('photo'));

        $this->assertSame('public', $upload->getDiskName());
        $this->assertFalse($upload->shouldFetchFileInformation());
    }
}
