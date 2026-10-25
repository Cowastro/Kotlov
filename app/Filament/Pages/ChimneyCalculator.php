<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use App\Models\ChimneyCalculationRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class ChimneyCalculator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Калькулятор дымохода';

    protected static ?string $title = 'Калькулятор дымохода';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'chimney-calculator';

    protected string $view = 'filament.pages.chimney-calculator';

    public ?int $calculationId = null;

    public function mount(): void
    {
        $this->calculationId = request()->integer('calculation') ?: null;
        if ($this->calculationId) {
            ChimneyCalculationRequest::query()->findOrFail($this->calculationId);
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCalculation')
                ->label('Новый расчёт')
                ->icon('heroicon-o-plus')
                ->url(static::getUrl())
                ->visible(fn (): bool => $this->calculationId !== null),
            Action::make('savedCalculations')
                ->label(fn (): string => 'Сохранённые расчёты'.(($count = ChimneyCalculationRequest::query()->where('status', 'pending')->count()) ? " ({$count})" : ''))
                ->icon('heroicon-o-archive-box')
                ->url(ChimneyCalculationRequestResource::getUrl('index')),
        ];
    }

    public function calculatorUrl(): string
    {
        $parameters = ['manager_mode' => 1];
        if (! $this->calculationId) {
            return route('chimney-selector', $parameters);
        }

        $calculation = ChimneyCalculationRequest::query()->findOrFail($this->calculationId);
        parse_str((string) parse_url((string) $calculation->source_url, PHP_URL_QUERY), $savedParameters);
        $parameters += collect($savedParameters)->only([
            'equipment', 'diameter', 'system', 'fuel', 'operation', 'steel', 'thickness', 'mono_material',
            'mono_length', 'stove_adapter', 'chimney_diameter', 'water_heating', 'scheme', 'elbow_angle',
            'height', 'horizontal_length', 'wall_pass', 'wall_mount_reach', 'offset_length', 'offset_system',
            'damper', 'ceiling_passes', 'clamps', 'wall_mounts', 'free_mast_height', 'guy_wires',
            'roof_type', 'roof_angle', 'ridge_distance', 'umbrella',
        ])->all();
        $parameters['calculation'] = $calculation->id;
        $parameters['kit'] = 1;
        $parameters['items'] = collect($calculation->items ?? [])
            ->filter(fn ($item): bool => is_array($item) && (int) ($item['product_id'] ?? 0) > 0)
            ->map(fn (array $item): string => (int) $item['product_id'].':'.max(1, (int) ($item['quantity'] ?? 1)))
            ->implode(',');

        return route('chimney-selector', $parameters);
    }
}
