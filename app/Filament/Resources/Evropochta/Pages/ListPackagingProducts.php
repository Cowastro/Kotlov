<?php

namespace App\Filament\Resources\Evropochta\Pages;

use App\Filament\Concerns\HasEvropochtaNavigation;
use App\Filament\Resources\Evropochta\PackagingResource;
use Filament\Resources\Pages\ListRecords;

class ListPackagingProducts extends ListRecords
{
    use HasEvropochtaNavigation;

    protected static string $resource = PackagingResource::class;
}
