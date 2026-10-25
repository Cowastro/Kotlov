<?php

namespace App\Filament\Pages;

use App\Models\ChimneyCalculationRequest;
use App\Services\ChimneyCalculationRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChimneyCalculator extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Калькулятор дымохода';

    protected static ?string $title = 'Калькулятор дымохода';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'chimney-calculator';

    protected string $view = 'filament.pages.chimney-calculator';

    public ?int $calculationId = null;

    public bool $showRequests = false;

    public function mount(): void
    {
        $this->showRequests = request()->boolean('requests');
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
                ->action(fn () => $this->startNewCalculation())
                ->visible(fn (): bool => $this->calculationId !== null || $this->showRequests),
            Action::make('savedCalculations')
                ->label(fn (): string => 'Заявки'.(($count = ChimneyCalculationRequest::query()->where('status', 'pending')->count()) ? " ({$count})" : ''))
                ->icon('heroicon-o-archive-box')
                ->url(static::getUrl(['requests' => 1]))
                ->visible(fn (): bool => ! $this->showRequests),
            Action::make('confirmCalculation')
                ->label('Создать заказ')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->currentCalculation()?->status === 'pending')
                ->requiresConfirmation()
                ->modalDescription('Состав и сумма будут зафиксированы, после чего появится обычный заказ для дальнейшей работы.')
                ->action(function (ChimneyCalculationRequestService $service): void {
                    $calculation = $this->currentCalculation();
                    if (! $calculation) {
                        return;
                    }

                    $user = auth()->user();
                    $result = $service->confirm($calculation, $user?->name ?? 'Менеджер CRM', $user);
                    Notification::make()->title('Заказ '.$result['order']->number.' создан')->success()->send();
                    $this->redirect(static::getUrl(['calculation' => $calculation->id]));
                }),
            Action::make('openOrder')
                ->label('Открыть заказ')
                ->icon('heroicon-o-shopping-cart')
                ->visible(fn (): bool => (bool) $this->currentCalculation()?->order_id)
                ->url(fn (): string => url('/admin/orders/'.$this->currentCalculation()?->order_id)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ChimneyCalculationRequest::query()->with('order'))
            ->columns([
                TextColumn::make('number')->label('Заявка')->searchable()->sortable()->weight('bold'),
                TextColumn::make('customer_name')->label('Клиент')->searchable()->description(fn (ChimneyCalculationRequest $record): string => $record->customer_phone),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn ($state) => ChimneyCalculationRequest::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning', 'converted' => 'success', 'cancelled' => 'danger', default => 'gray'
                    }),
                TextColumn::make('total')->label('Итого')->money('BYN')->sortable(),
                TextColumn::make('order.number')->label('Заказ')->placeholder('—'),
                TextColumn::make('created_at')->label('Получена')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(ChimneyCalculationRequest::STATUSES),
            ])
            ->recordUrl(fn (ChimneyCalculationRequest $record): string => static::getUrl(['calculation' => $record->id]));
    }

    public function currentCalculation(): ?ChimneyCalculationRequest
    {
        return $this->calculationId
            ? ChimneyCalculationRequest::query()->with('order')->find($this->calculationId)
            : null;
    }

    public function startNewCalculation(): void
    {
        $this->calculationId = null;
        $this->showRequests = false;
        $this->redirect(static::getUrl(['fresh' => 1]), navigate: false);
    }

    public function calculatorUrl(): string
    {
        $parameters = ['manager_mode' => 1];
        if (request()->boolean('fresh')) {
            $parameters['fresh'] = 1;
        }
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
