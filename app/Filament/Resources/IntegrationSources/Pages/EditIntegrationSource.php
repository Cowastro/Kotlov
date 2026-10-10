<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Models\SupplierChannelTransition;
use App\Services\Integrations\SupplierChannelTransitionPlanner;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Throwable;

class EditIntegrationSource extends EditRecord
{
    protected static string $resource = IntegrationSourceResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewTransition')
                ->label('Проверить переход на 1С')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->modalHeading('Безопасный предпросмотр перехода')
                ->modalDescription(fn (): HtmlString => new HtmlString($this->transitionPreviewHtml()))
                ->modalSubmitActionLabel('Сохранить проверку в журнал')
                ->action(function (): void {
                    try {
                        $transition = app(SupplierChannelTransitionPlanner::class)
                            ->recordPreview($this->record, auth()->id());
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Предпросмотр недоступен')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    $blockers = count($transition->snapshot['blockers'] ?? []);

                    Notification::make()
                        ->title('Проверка сохранена')
                        ->body($blockers === 0
                            ? 'Конфликтов нет. Теперь нужен новый контрольный обмен 1С; старый канал не изменён.'
                            : "Обнаружено блокировок: {$blockers}. Старый канал не изменён.")
                        ->color($blockers === 0 ? 'success' : 'warning')
                        ->send();
                }),
            Action::make('confirmTransition')
                ->label('Переключить рабочий канал на 1С')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->visible(fn (): bool => app(SupplierChannelTransitionPlanner::class)
                    ->latest($this->record)?->status === SupplierChannelTransition::STATUS_READY)
                ->requiresConfirmation()
                ->modalHeading('Подтвердить переход на 1С')
                ->modalDescription('После подтверждения операционный подбор цен, остатков и поставщика перестанет использовать старый канал этого поставщика. Старые связи не удаляются и доступны для отката.')
                ->modalSubmitActionLabel('Переключить на 1С')
                ->action(function (): void {
                    try {
                        app(SupplierChannelTransitionPlanner::class)
                            ->confirmSwitch($this->record, auth()->id());

                        Notification::make()
                            ->success()
                            ->title('Рабочий канал переключён на 1С')
                            ->body('Старые связи сохранены, но исключены из операционного подбора. При необходимости доступен журналируемый откат.')
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Переключение заблокировано')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
            Action::make('rollbackTransition')
                ->label('Вернуть старый канал')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => app(SupplierChannelTransitionPlanner::class)
                    ->latest($this->record)?->status === SupplierChannelTransition::STATUS_LEGACY_DISABLED)
                ->schema([
                    Textarea::make('reason')
                        ->label('Причина отката')
                        ->helperText('Причина сохранится в журнале перехода.')
                        ->required()
                        ->maxLength(1000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Вернуть старый канал в операционный подбор?')
                ->modalDescription('Связи 1С сохранятся. До нового подтверждённого перехода система снова сможет использовать предложения старого канала.')
                ->modalSubmitActionLabel('Выполнить откат')
                ->action(function (array $data): void {
                    try {
                        app(SupplierChannelTransitionPlanner::class)
                            ->rollback($this->record, auth()->id(), (string) ($data['reason'] ?? ''));

                        Notification::make()
                            ->warning()
                            ->title('Старый канал возвращён')
                            ->body('Откат записан в журнал. Связи 1С не изменены.')
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Откат не выполнен')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    private function transitionPreviewHtml(): string
    {
        try {
            $preview = app(SupplierChannelTransitionPlanner::class)->preview($this->record);
        } catch (Throwable $exception) {
            return '<p>'.e($exception->getMessage()).'</p>';
        }

        $rows = [
            'Карточек старого канала' => $preview['legacy_products'],
            'Подтверждённых карточек 1С' => $preview['matched_products'],
            'Общих карточек' => $preview['shared_products'],
            'Только в старом канале' => $preview['legacy_only_products'],
            'Непривязанных товаров в наличии' => $preview['unmatched_in_stock'],
            'Товаров в наличии без цены' => $preview['missing_price_in_stock'],
            'Дублирующих привязок' => $preview['duplicate_target_products'],
        ];
        $html = '<div class="space-y-3"><dl class="grid grid-cols-2 gap-2">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt class="text-sm text-gray-500">'.e($label).'</dt>'
                .'<dd class="font-semibold">'.number_format((int) $value, 0, ',', ' ').'</dd></div>';
        }
        $html .= '</dl>';

        if ($preview['blockers'] === []) {
            $html .= '<p class="text-success-600">Конфликтов нет. Сохранение только зафиксирует снимок; старый канал останется включён до отдельного контрольного обмена.</p>';
        } else {
            $html .= '<p class="font-semibold text-danger-600">Отключение старого канала заблокировано:</p><ul class="list-disc pl-5">';
            foreach ($preview['blockers'] as $blocker) {
                $html .= '<li>'.e($blocker).'</li>';
            }
            $html .= '</ul>';
        }

        return $html.'</div>';
    }
}
