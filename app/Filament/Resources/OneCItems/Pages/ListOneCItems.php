<?php

namespace App\Filament\Resources\OneCItems\Pages;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\OneCGroupCreator;
use App\Services\Integrations\OneCBridge\OneCGroupTree;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

class ListOneCItems extends ListRecords
{
    protected static string $resource = OneCItemResource::class;

    public string $groupSearch = '';

    public function content(Schema $schema): Schema
    {
        $content = parent::content($schema)->getComponents();

        return $schema->components([View::make('filament.onec.group-tree'), ...$content]);
    }

    #[Computed]
    public function groupTree(): array
    {
        return app(OneCGroupTree::class)->nodes($this->groupSearch, data_get($this->tableFilters, 'group.value'));
    }

    #[Computed]
    public function groupTasks()
    {
        return OneCAgentTask::query()->whereNotNull('payload->create_group')->latest('id')->limit(20)->get()
            ->unique(fn (OneCAgentTask $task): string => $task->payload['group_operation'])->take(5);
    }

    public function selectGroup(?string $key): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->isManager(), 403);
        if ($key) {
            abort_unless(OneCItem::query()->where('ref_key', $key)->where('is_folder', true)->where('deletion_mark', false)->exists(), 404);
        }
        $this->tableFilters['group']['value'] = $key;
        $this->updatedTableFilters();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $sync = app(OneCNomenclature::class)->lastSync();
        $items = OneCItem::query()->where('is_folder', false)->where('deletion_mark', false);
        $total = (clone $items)->count();
        $linked = (clone $items)->where(fn ($q) => $q->whereNotNull('product_id')->orWhere('site_link_count', '>', 0))->count();
        $site = (clone $items)->where('created_from_site', true)->count();
        $dupes = (clone $items)->where('same_name_count', '>', 1)->count();

        return new HtmlString('Справочник «Номенклатура» из 1С: товары, группы и связь с карточками сайта. Привязывайте существующие карточки кнопкой в строке; каждое исполнение — отдельно. Просмотр и привязка не меняют цены и остатки в 1С; '
            .'новый товар создаётся кнопкой «Создать в 1С» в карточке или в заказе, новая папка — кнопкой «Создать группу в 1С». Обновляется каждое утро. '
            .($sync ? 'Прочитано '.$sync->finished_at?->timezone('Europe/Minsk')->format('d.m.Y H:i').'. ' : '<span style="color:#dc2626">Ещё не прочитано — нажмите «Обновить из 1С».</span> ')
            .'<br>Товаров: <b>'.$total.'</b>, привязано к сайту: <b>'.$linked.'</b>, созданы с сайта: <b>'.$site.'</b>'
            .($dupes ? ', <span style="color:#dc2626">одинаковые названия в группе: <b>'.$dupes.'</b></span>' : '').'.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createGroup')->label('Создать группу в 1С')->icon('heroicon-o-folder-plus')
                ->visible(fn (): bool => (bool) auth()->user()?->is_active && auth()->user()?->isManager())
                ->modalHeading('Новая группа номенклатуры 1С')
                ->modalDescription('Группа будет создана в выбранной папке 1С. Сначала проверим, нет ли там такой группы. Категории сайта создаются отдельно.')
                ->modalSubmitActionLabel('Проверить и создать')
                ->fillForm(fn (): array => ['parent' => data_get($this->tableFilters, 'group.value')])
                ->schema([
                    Select::make('parent')->label('Родительская группа')->placeholder('Корень номенклатуры')
                        ->options(fn (): array => app(OneCNomenclature::class)->folderOptions())->searchable()->preload(),
                    TextInput::make('name')->label('Название новой группы')->required()->maxLength(100),
                ])
                ->action(function (array $data): void {
                    try {
                        app(OneCGroupCreator::class)->request($data['name'], $data['parent'] ?? null, auth()->user());
                        Notification::make()->success()->title('Группа отправлена на проверку в 1С')
                            ->body('Ответ появится под деревом групп после работы агента, обычно в течение 5 минут.')->send();
                    } catch (DomainException $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }
                }),
            Action::make('sync')->label('Обновить из 1С')->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Прочитать всю номенклатуру из 1С?')
                ->modalDescription('Агент только читает: все товары и группы. Через 5–10 минут таблица обновится.')
                ->action(function (): void {
                    try {
                        $task = app(OneCNomenclature::class)->enqueue(auth()->user());
                        Notification::make()->success()->title('Задача №'.$task->id.' в очереди агента 1С')->send();
                    } catch (DomainException $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }
                }),
            // the price batches for 1C: the drafts «Задать цены» fills, sent to 1C from there
            Action::make('priceBatches')->label('Цены для 1С')->color('warning')->icon('heroicon-o-currency-dollar')
                ->url(OneCPriceBatchResource::getUrl('index')),
            Action::make('bridge')->label('Мост 1С')->color('gray')->icon('heroicon-o-arrows-right-left')
                ->url(OneCAgentTaskResource::getUrl('index')),
        ];
    }
}
