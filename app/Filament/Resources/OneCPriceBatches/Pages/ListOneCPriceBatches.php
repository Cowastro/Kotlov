<?php

namespace App\Filament\Resources\OneCPriceBatches\Pages;

use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\OneCAgentToken;
use App\Services\Integrations\OneCPriceBatches;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ListOneCPriceBatches extends ListRecords
{
    protected static string $resource = OneCPriceBatchResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        $agent = OneCAgentToken::query()->whereNull('revoked_at')->max('last_seen_at');

        return 'Опт и розница для товаров 1С: сформируйте пакет, проверьте цены и отправьте. Агент на сервере 1С создаст непроведённую «Установку цен номенклатуры». '
            .($agent ? 'Агент на связи: '.Carbon::parse($agent)->timezone('Europe/Minsk')->format('d.m.Y H:i').'.' : 'Агент ещё не подключался.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buildPriceless')
                ->label('Сформировать: товары без цены')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Сформировать пакет из товаров 1С без цены?')
                ->modalDescription('В пакет попадут товары с остатком, у которых в 1С нет цены и розница прошла проверку (наценка к закупке, рынок). Опт — розница −20 %, не ниже закупки +5 %. Цены можно будет поправить перед отправкой.')
                ->action(function (): void {
                    $batch = app(OneCPriceBatches::class)->buildFromPriceless(auth()->user());
                    Notification::make()->success()->title('Пакет №'.$batch->id.' сформирован: товаров '.$batch->items_count)->send();
                    $this->redirect(OneCPriceBatchResource::getUrl('view', ['record' => $batch]));
                }),
            Action::make('agentKey')
                ->label('Ключ агента 1С')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->modalHeading('Выпустить новый ключ агента?')
                ->modalDescription('Прежние ключи перестанут работать. Новый ключ показывается один раз: сразу вставьте его в настройку агента на сервере 1С.')
                ->action(function (): void {
                    OneCAgentToken::query()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                    [, $plain] = OneCAgentToken::issue('Агент сервера 1С', auth()->id());
                    Notification::make()->success()->persistent()->title('Ключ агента (показывается один раз)')
                        ->body(new HtmlString('<code style="user-select:all;word-break:break-all">'.e($plain).'</code>'))->send();
                }),
        ];
    }
}
