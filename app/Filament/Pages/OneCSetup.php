<?php

namespace App\Filament\Pages;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Models\IntegrationSource;
use App\Services\Integrations\OneCSetupReadiness;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class OneCSetup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Настройка 1С';

    protected static ?string $title = 'Настройка автоматического обмена 1С';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.one-c-setup';

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $readiness = app(OneCSetupReadiness::class);
        $sources = IntegrationSource::query()
            ->where('driver', 'commerceml')
            ->orderBy('name')
            ->get()
            ->map(fn (IntegrationSource $source): array => $readiness->snapshot($source))
            ->all();

        return [
            'sources' => $sources,
            'sourceListUrl' => IntegrationSourceResource::getUrl('index'),
            'journalUrl' => IntegrationExchangeRunResource::getUrl('index'),
        ];
    }
}
