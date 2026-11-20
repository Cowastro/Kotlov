<?php

namespace App\Filament\Resources\OneCPriceBatches\Pages;

use App\Filament\Resources\OneCAgentTasks\Actions\IssueAgentKeyAction;
use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\OneCAgentToken;
use App\Services\Integrations\OneCPriceBatches;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;

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
            IssueAgentKeyAction::make(),
        ];
    }
}
