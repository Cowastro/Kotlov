<?php

namespace App\Filament\Resources\VamExpress\Pages;

use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Services\Shipping\VamExpressSettings;
use App\Support\Shipping\VamExpressStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListVamExpressShipments extends ListRecords
{
    protected static string $resource = VamExpressShipmentResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => 'Все', 'work' => 'В работе', 'transit' => 'В пути', 'delivered' => 'Доставлены', 'attention' => 'Переносы / отказы'];
        $result = [];
        foreach ($tabs as $key => $label) {
            $result[$key] = Tab::make($label)->modifyQueryUsing(fn (Builder $query): Builder => VamExpressStatus::scope($query, $key));
        }

        return $result;
    }

    public function getSubheading(): ?string
    {
        $settings = app(VamExpressSettings::class);
        $data = $settings->get();
        if (! $settings->configured()) {
            return 'Синхронизация выключена. Настройки подключения доступны администратору.';
        }
        $last = filled($data['last_success_at'] ?? null) ? Carbon::parse($data['last_success_at'])->format('d.m.Y H:i') : 'ещё не было';

        return 'Обновление: '.$last.'. '.($data['message'] ?? 'Ожидаем первую загрузку.').' Свежие заявки — каждые 30 минут. Для старых используйте «Загрузить по ID».';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                Action::make('calculator')->label('Быстрый расчёт')->url(fn (): string => \App\Filament\Pages\VamExpressCalculator::getUrl()),
                Action::make('drafts')->label('Черновики доставки')->url(fn (): string => \App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource::getUrl('index')),
            ])->label('Подготовить доставку')->icon('heroicon-o-calculator')->button()->color('gray'),
            Action::make('resetSelection')->label('Сбросить всё')->icon('heroicon-o-x-mark')->color('gray')
                ->authorize(fn (): bool => VamExpressShipmentResource::canViewAny())
                ->visible(fn (): bool => $this->activeTab !== 'all' || filled($this->tableSearch)
                    || collect($this->tableFilters ?? [])->flatten()->contains(fn ($value): bool => filled($value)))
                ->action(function (): void {
                    $this->activeTab = 'all';
                    $this->removeTableFilters();
                    $this->resetPage();
                }),
            Action::make('connection')->label('Настройки входа')->icon('heroicon-o-key')
                ->modalSubmitActionLabel('Сохранить')
                ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                ->authorize(fn (): bool => auth()->user()?->isAdmin() === true)
                ->modalDescription('Данные от vamexpress.by хранятся зашифрованными. Загружаются только существующие заявки. После сохранения первая проверка начнётся в течение минуты.')
                ->fillForm(function (): array {
                    abort_unless(auth()->user()?->isAdmin(), 403);
                    $data = app(VamExpressSettings::class)->get();

                    return ['username' => $data['username'] ?? '', 'password' => '', 'enabled' => $data['enabled'] ?? true];
                })->schema([
                    TextInput::make('username')->label('Логин ВАМ Экспресс')->required()->maxLength(255),
                    TextInput::make('password')->label('Пароль')->password()->autocomplete('new-password')->maxLength(255)
                        ->required(fn (): bool => blank(app(VamExpressSettings::class)->get()['password'] ?? null))
                        ->helperText('Оставьте пустым, чтобы сохранить прежний пароль.'),
                    Toggle::make('enabled')->label('Читать и обновлять заявки')->default(true),
                ])->action(function (array $data, Action $action): void {
                    abort_unless(auth()->user()?->isAdmin(), 403);
                    try {
                        app(VamExpressSettings::class)->save($data['username'], $data['password'] ?? null, (bool) $data['enabled']);
                        Notification::make()->success()->title('Настройки сохранены')->body('Результат загрузки появится в этом разделе.')->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();
                    }
                }),
            Action::make('sync')->label('Обновить заявки')->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => VamExpressShipmentResource::canViewAny())
                ->action(fn (Action $action) => $this->requestSync($action)),
            Action::make('find')->label('Загрузить по ID')->icon('heroicon-o-magnifying-glass')
                ->modalSubmitActionLabel('Загрузить')
                ->authorize(fn (): bool => VamExpressShipmentResource::canViewAny())
                ->modalDescription('Для старой заявки укажите короткий ID из списка ВАМ или полный ID из адреса заявки. После загрузки её можно привязать к заказу.')
                ->schema([TextInput::make('id')->label('ID ВАМ Экспресс')->required()->trim()->maxLength(32)->regex('/^(?:[a-f0-9]{7}|[a-f0-9]{32})$/i')])
                ->action(fn (array $data, Action $action) => $this->requestSync($action, $data['id'])),
        ];
    }

    private function requestSync(Action $action, ?string $id = null): void
    {
        abort_unless(VamExpressShipmentResource::canViewAny(), 403);
        try {
            app(VamExpressSettings::class)->requestSync($id);
            Notification::make()->success()->title('Обновление запрошено')
                ->body('Загрузка выполняется в фоне. Обычно начинается в течение минуты; между попытками выдерживается пауза не менее двух минут. Обновите страницу позже.')->send();
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }
    }
}
