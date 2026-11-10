<?php

namespace App\Filament\Resources\CrmMailingLists\RelationManagers;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Models\CrmCounterparty;
use App\Models\CrmMailingListMember;
use App\Services\Crm\MailingAudience;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Партнёры в рассылке';

    /** @var array<int, array{emails:array<int,string>,phones:array<int,string>,messengers:array<int,string>}> */
    private array $channelCache = [];

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['counterparty', 'b2bClient.user', 'supplier']))
            ->description('Контакты собраны из карточки контрагента и его контактных лиц, B2B-кабинета, филиалов и карточки поставщика.')
            ->columns([
                TextColumn::make('name')->label('Партнёр')->weight('bold')->searchable()->sortable()
                    ->description(fn (CrmMailingListMember $record): ?string => $record->query && $record->query !== $record->name ? 'искали: '.$record->query : null)
                    ->url(fn (CrmMailingListMember $record): ?string => $record->counterparty_id ? CrmCounterpartyResource::getUrl('view', ['record' => $record->counterparty_id]) : null),
                TextColumn::make('emails')->label('E-mail')->state(fn (CrmMailingListMember $record): ?string => $this->channel($record, 'emails'))->copyable()->wrap()->placeholder('нет'),
                TextColumn::make('phones')->label('Телефоны')->state(fn (CrmMailingListMember $record): ?string => $this->channel($record, 'phones'))->copyable()->wrap()->placeholder('нет'),
                TextColumn::make('messengers')->label('Мессенджеры')->state(fn (CrmMailingListMember $record): ?string => $this->channel($record, 'messengers'))->wrap()->placeholder('—'),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('addCounterparty')
                    ->label('Добавить контрагента')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Select::make('counterparty_id')->label('Контрагент')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => CrmCounterparty::query()
                                ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('legal_name', 'like', "%{$search}%"))
                                ->limit(30)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => CrmCounterparty::query()->whereKey($value)->value('name')),
                    ])
                    ->action(function (array $data): void {
                        $id = (int) $data['counterparty_id'];
                        $added = app(MailingAudience::class)->addMember($this->getOwnerRecord(), ['kind' => 'counterparty', 'id' => $id, 'counterparty' => $id]);
                        Notification::make()->success()->title($added ? 'Добавлен в рассылку' : 'Уже есть в списке')->send();
                    }),
            ])
            ->recordActions([DeleteAction::make()->label('Убрать')->modalHeading('Убрать из рассылки?')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->label('Убрать из рассылки')])]);
    }

    private function channel(CrmMailingListMember $member, string $type): ?string
    {
        $this->channelCache[$member->id] ??= app(MailingAudience::class)->channels($member);

        return implode(', ', $this->channelCache[$member->id][$type]) ?: null;
    }
}
