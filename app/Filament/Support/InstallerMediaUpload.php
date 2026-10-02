<?php

namespace App\Filament\Support;

use App\Support\InstallerMedia;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;

class InstallerMediaUpload
{
    /**
     * Store new files publicly and retain legacy public/img paths during
     * hydration so editing unrelated fields cannot silently erase media.
     */
    public static function configure(FileUpload $upload): FileUpload
    {
        return $upload
            ->disk('public')
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(static function (
                BaseFileUpload $component,
                string $file,
                string|array|null $storedFileNames,
            ): ?array {
                if (InstallerMedia::isLegacyPublicPath($file)) {
                    return InstallerMedia::fileUploadMetadata($file, $storedFileNames);
                }

                return $component->getUploadedFile($file, $storedFileNames);
            });
    }
}
