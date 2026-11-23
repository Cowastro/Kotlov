<?php

namespace App\Filament\Resources\OneCItems\Pages;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListOneCItems extends ListRecords
{
    protected static string $resource = OneCItemResource::class;

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

        return new HtmlString('Копия справочника «Номенклатура» из 1С: товары, группы и связь с карточками сайта. Привязывайте существующие карточки кнопкой в строке; каждое исполнение — отдельно. Цены, остатки и данные в 1С не меняются; '
            .'новый товар создаётся кнопкой «Создать в 1С» в карточке или в заказе. Обновляется каждое утро. '
            .($sync ? 'Прочитано '.$sync->finished_at?->timezone('Europe/Minsk')->format('d.m.Y H:i').'. ' : '<span style="color:#dc2626">Ещё не прочитано — нажмите «Обновить из 1С».</span> ')
            .'<br>Товаров: <b>'.$total.'</b>, привязано к сайту: <b>'.$linked.'</b>, созданы с сайта: <b>'.$site.'</b>'
            .($dupes ? ', <span style="color:#dc2626">одинаковые названия в группе: <b>'.$dupes.'</b></span>' : '').'.');
    }

    protected function getHeaderActions(): array
    {
        return [
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
            Action::make('bridge')->label('Мост 1С')->color('gray')->icon('heroicon-o-arrows-right-left')
                ->url(OneCAgentTaskResource::getUrl('index')),
        ];
    }
}
