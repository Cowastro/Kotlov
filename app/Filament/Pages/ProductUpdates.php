<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ProductUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'Обновление товаров';
    protected static ?string $title = 'Обновление товаров';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.product-updates';

    public ?string $lastOutput = null;
    public ?int $lastExitCode = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dry_run_elicon')
                ->label('Пробный запуск')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->form($this->optionsForm())
                ->action(fn (array $data) => $this->runElicon(false, $data)),

            Action::make('apply_elicon')
                ->label('Обновить Эликон')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Обновить товары Эликон?')
                ->modalDescription('Будут обновлены цены, наличие, карточки, атрибуты и фото счетчиков газа из elicon.by.')
                ->form($this->optionsForm())
                ->action(fn (array $data) => $this->runElicon(true, $data)),
        ];
    }

    private function optionsForm(): array
    {
        return [
            Toggle::make('no_images')
                ->label('Не скачивать фото')
                ->default(false),

            TextInput::make('limit')
                ->label('Лимит товаров')
                ->numeric()
                ->minValue(1)
                ->placeholder('Все товары'),
        ];
    }

    public function runElicon(bool $apply, array $data = []): void
    {
        $parameters = [];

        if ($apply) {
            $parameters['--apply'] = true;
        }

        if (! empty($data['no_images'])) {
            $parameters['--no-images'] = true;
        }

        if (! empty($data['limit'])) {
            $parameters['--limit'] = (int) $data['limit'];
        }

        $this->lastExitCode = Artisan::call('supplier:sync-elicon-gas-meters', $parameters);
        $this->lastOutput = trim(Artisan::output());

        $notification = Notification::make()
            ->title($this->lastExitCode === 0 ? 'Команда завершена' : 'Команда завершилась с ошибками')
            ->body(Str::limit($this->lastOutput ?: 'Вывод команды пуст.', 1200))
            ->persistent();

        $this->lastExitCode === 0 ? $notification->success() : $notification->danger();
        $notification->send();
    }
}
