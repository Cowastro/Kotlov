<?php

namespace App\Filament\Resources\Products\Actions;

use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Models\IntegrationProduct;
use App\Models\OneCItem;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use App\Services\Integrations\OneCBridge\ItemTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

/**
 * «1С» on a card: «1С: УТ-…» when the card has its 1C item, otherwise «Создать в 1С»: the manager first sees the 1C
 * items that may already be this card (link one), or creates the item after a similar 1C item.
 */
class ProductOneCItemAction
{
    public static function make(string $name = 'onecItem', ?\Closure $product = null): Action
    {
        $card = fn ($record): Product => $product ? $product($record) : $record;

        return Action::make($name)
            ->label(fn ($record): string => self::label($card($record)))
            ->icon('heroicon-o-cube')
            ->color(fn ($record): string => app(OneCItemCreator::class)->linked($card($record)) ? 'info' : 'gray')
            ->visible(fn (): bool => (bool) auth()->user()?->isManager() && app(OneCItemCreator::class)->source() !== null)
            ->modalHeading(fn ($record): string => app(OneCItemCreator::class)->linked($card($record)) ? 'Товар в 1С' : 'Создать товар в 1С')
            ->modalDescription(fn ($record): HtmlString => self::description($card($record)))
            ->modalSubmitActionLabel('Привязать или создать в 1С')
            ->modalSubmitAction(fn ($record, Action $action) => self::busy($card($record)) ? false : $action)
            ->fillForm(fn ($record): array => ['existing' => app(OneCItemCreator::class)->obviousMatch($card($record))?->id,
                'template' => $template = app(OneCItemCreator::class)->defaultTemplate($card($record)),
                'group' => app(OneCItemCreator::class)->templateGroup($template),
                'name' => mb_substr((string) $card($record)->name, 0, 100)])
            ->schema(fn ($record): array => self::busy($card($record)) ? [] : self::schema($card($record)))
            ->action(function ($record, array $data) use ($card): void {
                $product = $card($record);
                try {
                    if ($data['existing'] ?? null) {
                        $row = IntegrationProduct::query()->find((int) $data['existing']);
                        if (! $row || ! app(IntegrationProductMatchDecision::class)->linkSelectedProduct($row, $product->id)) {
                            throw new DomainException('Этот товар 1С уже привязан к другой карточке — выберите другой.');
                        }
                        app(OneCNomenclature::class)->remember((string) OneCItemCreator::itemKey($row), (string) $row->external_code, (string) $row->name, $product->id, null, null, false);
                        Notification::make()->success()->title('Карточка привязана к товару 1С '.($row->external_code ?: $row->name))->send();

                        return;
                    }
                    $task = app(OneCItemCreator::class)->request($product, (string) ($data['template'] ?? ''), $data['name'] ?? null, auth()->user(), $data['group'] ?? null);
                    Notification::make()->success()->title('Товар создаётся в 1С (задача №'.$task->id.')')
                        ->body('Агент проверит, нет ли его в 1С, и создаст по образцу в течение 5 минут. Карточка привяжется сама.')->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Товар не создан')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /** Linked already, or a creation is under way: the window only tells about it. */
    private static function busy(Product $product): bool
    {
        return app(OneCItemCreator::class)->linked($product) !== null || self::waiting($product);
    }

    private static function waiting(Product $product): bool
    {
        $tasks = app(OneCAgentTasks::class);

        return $tasks->activeFor($product, ODataReadTaskHandler::TYPE) !== null || $tasks->activeFor($product, ItemTaskHandler::TYPE) !== null;
    }

    private static function label(Product $product): string
    {
        $linked = app(OneCItemCreator::class)->linked($product);

        return match (true) {
            $linked !== null => '1С: '.($linked->external_code ?: 'связан'),
            self::waiting($product) => 'Создаётся в 1С',
            default => 'Создать в 1С',
        };
    }

    private static function description(Product $product): HtmlString
    {
        $linked = app(OneCItemCreator::class)->linked($product);
        if ($linked) {
            $item = OneCItem::query()->where('ref_key', OneCItemCreator::itemKey($linked))->first();

            return new HtmlString('Карточка связана с товаром 1С <b>'.e($linked->external_code ?: '').'</b> «'.e((string) $linked->name).'»'
                .($item?->path ? '<br>Группа в 1С: <b>'.e($item->path).'</b>' : '')
                .'<br><a href="'.e(OneCItemResource::getUrl('index', ['tableSearch' => $linked->external_code ?: $linked->name])).'" style="text-decoration:underline">Показать в номенклатуре 1С</a>');
        }
        if (self::waiting($product)) {
            return new HtmlString('Агент на сервере 1С проверяет и создаёт товар — в течение 5 минут карточка привяжется сама. Ход — в «Мост 1С».');
        }

        return new HtmlString('Сначала проверьте, нет ли этого товара в 1С: если есть — привяжите его, второй создавать нельзя. '
            .'Новый товар создаётся <b>по образцу</b> похожего товара 1С: вид номенклатуры, единица, НДС, группа и учёт копируются, '
            .'наименование и артикул берутся из карточки. Перед созданием агент ещё раз ищет в 1С тот же артикул и наименование.');
    }

    private static function schema(Product $product): array
    {
        $creator = app(OneCItemCreator::class);
        $similar = $creator->similar($product);

        return [
            Placeholder::make('similar')->label('Похожие товары в 1С')
                ->content(new HtmlString($similar === [] ? '<span style="color:#16a34a">Похожих не нашлось</span>'
                    : '<ul style="margin:0;padding-left:18px">'.collect($similar)->map(fn (string $s): string => '<li>'.e($s).'</li>')->implode('').'</ul>')),
            Select::make('existing')->label('Этот товар уже есть в 1С — привязать к нему')->searchable()
                ->placeholder('Найдите по названию или коду 1С')
                ->getSearchResultsUsing(fn (string $search): array => app(OneCOrderExport::class)->searchItems($search))
                ->getOptionLabelUsing(fn ($value): ?string => app(OneCOrderExport::class)->itemLabel((int) $value))
                ->helperText('Если товар выбран — он не создаётся, карточка просто привязывается к нему. Сайт сам подставляет самый похожий свободный товар 1С: если это не он — очистите поле (×), тогда товар создастся по образцу.'),
            Select::make('template')->label('Образец — похожий товар 1С')->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $creator->searchTemplates($search))
                ->getOptionLabelUsing(fn ($value): ?string => $creator->templateLabel((string) $value))
                ->requiredWithout('existing')->live()
                ->afterStateUpdated(fn (?string $state, Set $set) => $set('group', $creator->templateGroup($state)))
                ->helperText('По умолчанию — самый похожий по названию товар 1С. Лучше выбрать тот же вид товара.'),
            Select::make('group')->label('Группа в 1С')->searchable()
                ->options(fn (?string $state): array => app(OneCNomenclature::class)->folderOptions() + ($state ? [$state => $state] : []))
                ->helperText('По умолчанию — группа образца. Новый товар будет лежать в 1С в этой группе.'),
            TextInput::make('name')->label('Наименование в 1С')->maxLength(100)->requiredWithout('existing')
                ->helperText('До 100 символов. Полное наименование — название карточки. Артикул в 1С: '.(OneCItemCreator::article($product) ?: 'нет артикула на сайте')),
        ];
    }
}
