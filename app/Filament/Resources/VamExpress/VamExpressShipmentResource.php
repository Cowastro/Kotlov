<?php

namespace App\Filament\Resources\VamExpress;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpress\Pages\ListVamExpressShipments;
use App\Models\VamExpressShipment;
use App\Support\Shipping\VamExpressStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VamExpressShipmentResource extends Resource
{
    protected static ?string $model = VamExpressShipment::class;

    protected static ?string $slug = 'vam-express-shipments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'ВАМ Экспресс';

    protected static ?string $pluralModelLabel = 'Заявки ВАМ Экспресс';

    public static function getNavigationGroup(): ?string
    {
        return 'Доставки';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_active && auth()->user()?->isManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order')->where(fn (Builder $q) => $q->whereNull('order_id')
            ->orWhereIn('order_id', OrderResource::getEloquentQuery()->select('orders.id')));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('remote_created_at', 'desc')->columns([
            TextColumn::make('display_id')->label('ID ВАМ')->searchable(['display_id', 'remote_id'])->copyable(),
            TextColumn::make('sender_profile')->label('Профиль отправителя')->searchable()->wrap()->limit(55)
                ->placeholder('Профиль загружается')->tooltip(fn (VamExpressShipment $record): ?string => $record->sender_profile)
                ->extraAttributes(['style' => 'min-width:165px;max-width:220px']),
            TextColumn::make('order.number')->label('Наш заказ')->searchable()->placeholder('Не привязан')
                ->url(fn (VamExpressShipment $record): ?string => $record->order && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            TextColumn::make('city')->label('Город')->searchable()->wrap()->limit(55)
                ->extraAttributes(['style' => 'min-width:140px;max-width:200px']),
            TextColumn::make('cargo')->label('Груз')->searchable()->wrap()->limit(100)->toggleable()
                ->extraAttributes(['style' => 'min-width:220px;max-width:300px']),
            TextColumn::make('carrier_status')->label('Статус перевозчика')->badge()
                ->extraAttributes(['style' => 'min-width:130px'])
                ->color(fn (?string $state): array => VamExpressStatus::color($state))
                ->icon(fn (?string $state): string => VamExpressStatus::icon($state)),
            TextColumn::make('delivery_cost')->label('Тариф ВАМ')->money('BYN'),
            TextColumn::make('total_amount')->label('Стоимость с доставкой')->money('BYN')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('remote_created_at')->label('Создана у ВАМ')->date('d.m.Y')->sortable(),
            TextColumn::make('accepted_at')->label('Принята')->date('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('dispatch_at')->label('Отправка')->date('d.m.Y')->sortable(),
            TextColumn::make('synced_at')->label('Проверено')->dateTime('d.m.Y H:i')->toggleable(),
        ])->filters([
            SelectFilter::make('sender_profile')->label('Профиль отправителя')->searchable()
                ->options(fn (): array => static::getEloquentQuery()->whereNotNull('sender_profile')->distinct()->orderBy('sender_profile')
                    ->pluck('sender_profile', 'sender_profile')->all()),
            TernaryFilter::make('order_id')->label('Привязка к заказу')->nullable()->trueLabel('Привязанные')->falseLabel('Без привязки'),
            SelectFilter::make('carrier_status')->label('Статус перевозчика')
                ->options(fn (): array => static::getEloquentQuery()->whereNotNull('carrier_status')->distinct()->pluck('carrier_status', 'carrier_status')->all()),
        ], layout: FiltersLayout::AboveContent)->filtersFormColumns(['default' => 1, 'md' => 3])
            ->deferFilters(false)->persistFiltersInSession()->persistSearchInSession()
            ->recordActions([
                ActionGroup::make([
                    Action::make('printA4')->label('А4')->icon('heroicon-o-printer')
                        ->url(fn (VamExpressShipment $record): string => route('admin.vam-express.label', ['shipment' => $record->id, 'format' => 'a4']))->openUrlInNewTab(),
                    Action::make('print78')->label('Лента 78 мм')->icon('heroicon-o-printer')
                        ->url(fn (VamExpressShipment $record): string => route('admin.vam-express.label', ['shipment' => $record->id, 'format' => '78']))->openUrlInNewTab(),
                ])->label('Этикетка')->icon('heroicon-o-printer')->button()->color('gray'),
                Action::make('linkOrder')->label('Привязать заказ')->icon('heroicon-o-link')
                    ->modalSubmitActionLabel('Сохранить привязку')
                    ->authorize(fn (): bool => static::canViewAny())
                    ->fillForm(fn (VamExpressShipment $record): array => ['order_id' => $record->order_id])
                    ->modalDescription('Выберите заказ KOTLOV. Статус и сумма заказа не изменятся. Пустое поле снимает привязку.')
                    ->schema([
                        Select::make('order_id')->label('Номер заказа или имя клиента')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => mb_strlen(trim($search)) < 2 ? [] : OrderResource::getEloquentQuery()
                                ->where(fn (Builder $q) => $q->where('number', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%'))
                                ->latest()->limit(20)->get()->filter(fn ($order) => OrderResource::canEdit($order))
                                ->mapWithKeys(fn ($order): array => [$order->id => $order->number.' · '.$order->customer_name])->all())
                            ->getOptionLabelUsing(function ($value): ?string {
                                $order = OrderResource::getEloquentQuery()->find($value);

                                return $order && OrderResource::canView($order) ? $order->number.' · '.$order->customer_name : null;
                            }),
                    ])->action(function (VamExpressShipment $record, array $data): void {
                        abort_unless(static::canViewAny(), 403);
                        DB::transaction(function () use ($record, $data): void {
                            $shipment = static::getEloquentQuery()->lockForUpdate()->findOrFail($record->id);
                            if ($shipment->order_id) {
                                abort_unless(OrderResource::canEdit($shipment->order), 403);
                            }
                            $order = filled($data['order_id'] ?? null) ? OrderResource::getEloquentQuery()->lockForUpdate()->findOrFail($data['order_id']) : null;
                            if ($order) {
                                abort_unless(OrderResource::canEdit($order), 403);
                            }
                            $shipment->forceFill(['order_id' => $order?->id])->save();
                        });
                        Notification::make()->success()->title('Привязка сохранена')->send();
                    }),
                Action::make('portal')->label('Открыть у ВАМ')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (VamExpressShipment $record): string => $record->portalUrl())->openUrlInNewTab(),
            ])->emptyStateHeading(fn (): string => static::getEloquentQuery()->exists() ? 'По выбранным условиям заявок нет' : 'Заявки ВАМ Экспресс ещё не загружены')
            ->emptyStateDescription(fn (): string => static::getEloquentQuery()->exists()
                ? 'Измените профиль, статус или поисковый запрос. «Сбросить всё» вернёт полный список.'
                : 'Администратор может указать данные входа в настройках этого раздела. После загрузки выберите заявку и привяжите её к заказу.');
    }

    public static function getPages(): array
    {
        return ['index' => ListVamExpressShipments::route('/')];
    }
}
