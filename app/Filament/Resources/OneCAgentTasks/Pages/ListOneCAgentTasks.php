<?php

namespace App\Filament\Resources\OneCAgentTasks\Pages;

use App\Filament\Resources\OneCAgentTasks\Actions\IssueAgentKeyAction;
use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCOrderProfiles\OneCOrderProfileResource;
use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OneCReferences;
use App\Services\Integrations\OneCPriceBatches;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListOneCAgentTasks extends ListRecords
{
    protected static string $resource = OneCAgentTaskResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $h = app(OneCAgentTasks::class)->health();
        $latest = (string) config('onec.agent.version');
        $parts = [];
        if ($h['seen_at'] === null) {
            $parts[] = '<span style="color:#dc2626">Агент 1С ещё не подключался.</span>';
        } else {
            $when = $h['seen_at']->timezone('Europe/Minsk')->format('d.m.Y H:i');
            $parts[] = $h['online']
                ? '<span style="color:#16a34a">● Агент 1С на связи</span> (последний раз '.$when.')'
                : '<span style="color:#dc2626">● Агент 1С молчит с '.$when.'</span>: проверьте сервер 1С: журнал запусков C:\kotlov-agent\task.log и задачу «KOTLOV 1C agent» в Планировщике.';
            $parts[] = 'Версия агента: '.e($h['version'] ?: '1').($h['outdated']
                ? ' — <span style="color:#d97706">устарела, скачайте agent.ps1 версии '.e($latest).' на сервер 1С</span>' : '').'.';
        }
        $parts[] = 'В очереди: '.$h['queued'].', с ошибкой: '.$h['failed'].'.';

        return new HtmlString('Задачи для 1С: сайт ставит, агент на сервере 1С выполняет каждые 5 минут и отвечает. Документы в 1С создаются непроведёнными.<br>'.implode(' ', $parts));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buildPriceless')
                ->label('Цены: товары без цены')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Сформировать пакет из товаров 1С без цены?')
                ->modalDescription('В пакет попадут товары с остатком, у которых в 1С нет цены и розница прошла проверку (наценка к закупке, рынок). Опт — розница −20 %, не ниже закупки +5 %. Цены можно будет поправить перед отправкой.')
                ->action(function (): void {
                    $batch = app(OneCPriceBatches::class)->buildFromPriceless(auth()->user());
                    Notification::make()->success()->title('Пакет №'.$batch->id.' сформирован: товаров '.$batch->items_count)->send();
                    $this->redirect(OneCPriceBatchResource::getUrl('view', ['record' => $batch]));
                }),
            Action::make('readOrderReferences')
                ->label('Обновить справочники 1С')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Обновить справочники 1С?')
                ->modalDescription('Агент только читает: организации, склады, партнёры, контрагенты, соглашения, договоры, подразделения, менеджеры, ставки НДС и заказы клиентов за 60 дней. В 1С ничего не меняется. Само обновление идёт каждое утро, заказы 1С — каждый час.')
                ->action(function (): void {
                    try {
                        $task = app(OneCReferences::class)->enqueueSync(auth()->user());
                        Notification::make()->success()->title('Задача №'.$task->id.' в очереди: агент прочитает за 5 минут')->send();
                    } catch (\DomainException $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }
                }),
            Action::make('checkDocuments')
                ->label('Сверить с 1С')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $task = app(DocumentStatusTaskHandler::class)->enqueueCheck(auth()->user());
                    $task
                        ? Notification::make()->success()->title('Агент проверит документы в 1С за 5 минут')->send()
                        : Notification::make()->info()->title('Все документы уже проведены, или проверка уже в очереди')->send();
                }),
            Action::make('orderProfiles')->label('Схемы заказов')->icon('heroicon-o-document-duplicate')->color('gray')
                ->url(OneCOrderProfileResource::getUrl('index')),
            Action::make('prices')->label('Пакеты цен')->icon('heroicon-o-currency-dollar')->color('gray')
                ->url(OneCPriceBatchResource::getUrl('index')),
            Action::make('download')->label('Скачать агента')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->url(url('/downloads/onec-agent/agent.ps1'))->openUrlInNewTab(),
            IssueAgentKeyAction::make(),
        ];
    }
}
