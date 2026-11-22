<?php

namespace App\Filament\Resources\OneCOrderProfiles\Pages;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCOrderProfiles\OneCOrderProfileResource;
use App\Models\OneCOrderProfile;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCReferences;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListOneCOrderProfiles extends ListRecords
{
    protected static string $resource = OneCOrderProfileResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $sync = app(OneCReferences::class)->lastSync();

        return new HtmlString('Схема — это то, как заказ заводится в 1С: организация, клиент, соглашение, склад, подразделение, условия оплаты. '
            .'Она делается по образцу настоящего заказа 1С: всё, что менеджер заполнил там, повторится в заказах с сайта. Справочники 1С: '
            .($sync ? 'обновлены '.$sync->finished_at?->timezone('Europe/Minsk')->format('d.m.Y H:i')
                : '<span style="color:#dc2626">ещё не прочитаны — нажмите «Обновить справочники 1С» в «Мост 1С»</span>').'.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromTemplate')
                ->label('Создать по образцу заказа 1С')
                ->icon('heroicon-o-document-duplicate')
                ->schema([
                    Select::make('document')->label('Документ 1С')->options(OneCOrderProfile::DOCUMENTS)->default(OneCOrderProfile::CUSTOMER_ORDER)->required()->live(),
                    Select::make('template')->label('Документ 1С — образец')->required()->searchable()
                        ->options(fn (Get $get): array => collect(app(OneCReferences::class)->templates(self::templatesOf($get('document'))))
                            ->mapWithKeys(fn (array $o, int $i): array => ['t'.$i => app(OneCReferences::class)->templateLabel($o)])->all())
                        ->helperText('Последние 40 заказов из 1С. Для розницы выберите заказ СБГ бай на «Розничного покупателя», для опта — заказ СанБизнесГруп на компанию.'),
                    TextInput::make('name')->label('Название схемы')->required()->placeholder('Розница СБГ бай'),
                    Select::make('customer_mode')->label('Покупатель')->options(OneCOrderProfile::CUSTOMER_MODES)->default(OneCOrderProfile::RETAIL)->required(),
                ])
                ->action(function (array $data): void {
                    $order = app(OneCReferences::class)->templates(self::templatesOf($data['document'] ?? null))[(int) substr((string) $data['template'], 1)] ?? null;
                    if (! $order) {
                        Notification::make()->danger()->title('Образец не найден: обновите справочники 1С')->send();

                        return;
                    }
                    $profile = app(OneCOrderProfiles::class)->fromTemplate($order, $data['name'], $data['customer_mode'],
                        $data['document'] ?? OneCOrderProfile::CUSTOMER_ORDER);
                    $this->redirect(OneCOrderProfileResource::getUrl('edit', ['record' => $profile]));
                }),
            Action::make('bridge')->label('Мост 1С')->color('gray')->icon('heroicon-o-arrows-right-left')
                ->url(OneCAgentTaskResource::getUrl('index')),
        ];
    }

    /** Which recent 1C documents a scheme of this kind is made from. */
    private static function templatesOf(?string $document): string
    {
        return $document === OneCOrderProfile::SUPPLIER_ORDER ? OneCReferences::SUPPLIER_TEMPLATES : OneCReferences::TEMPLATES;
    }
}
