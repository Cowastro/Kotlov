<?php

namespace App\Filament\Resources\CrmMailingLists\Pages;

use App\Filament\Resources\CrmMailingCampaigns\CrmMailingCampaignResource;
use App\Filament\Resources\CrmMailingLists\CrmMailingListResource;
use App\Models\CrmMailingList;
use App\Services\Crm\MailingAudience;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditCrmMailingList extends EditRecord
{
    protected static string $resource = CrmMailingListResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addByNames')
                ->label('Добавить по списку названий')
                ->icon('heroicon-o-magnifying-glass-plus')
                ->schema([
                    Textarea::make('names')->label('Названия партнёров — по одному в строке')->rows(14)->required()
                        ->helperText('Можно через «/» несколько вариантов: «Олещик/ТД Пинск». ИП, ООО, кавычки и пробелы не важны.'),
                ])
                ->action(function (array $data): void {
                    /** @var CrmMailingList $list */
                    $list = $this->record;
                    $stats = app(MailingAudience::class)->addByNames($list, (string) $data['names']);
                    $list->touch();
                    Notification::make()->success()->persistent()
                        ->title("Найдено {$stats['matched']} из {$stats['lines']}, добавлено {$stats['added']}")
                        ->body($stats['missing'] === [] ? 'Нашлись все.' : 'Не нашлись в базе: '.implode('; ', $stats['missing']))
                        ->send();
                }),
            Action::make('exportCsv')
                ->label('Скачать контакты (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            Action::make('newCampaign')
                ->label('Отправить Viber/SMS')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (): string => CrmMailingCampaignResource::getUrl('create', ['list' => $this->record->getKey()])),
            DeleteAction::make(),
        ];
    }

    private function exportCsv(): StreamedResponse
    {
        /** @var CrmMailingList $list */
        $list = $this->record;
        $audience = app(MailingAudience::class);

        return response()->streamDownload(function () use ($list, $audience): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['Партнёр', 'Как искали', 'E-mail', 'Телефоны', 'Мессенджеры'], ';');
            foreach ($list->members()->with(['counterparty', 'b2bClient.user', 'supplier'])->orderBy('name')->get() as $member) {
                $c = $audience->channels($member);
                fputcsv($out, [$member->name, $member->query, implode(', ', $c['emails']), implode(', ', $c['phones']), implode(', ', $c['messengers'])], ';');
            }
            fclose($out);
        }, 'rassylka-'.$list->id.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
