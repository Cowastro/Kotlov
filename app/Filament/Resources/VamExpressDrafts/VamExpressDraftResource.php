<?php

namespace App\Filament\Resources\VamExpressDrafts;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpressDrafts\Pages\CreateVamExpressDraft;
use App\Filament\Resources\VamExpressDrafts\Pages\EditVamExpressDraft;
use App\Filament\Resources\VamExpressDrafts\Pages\ListVamExpressDrafts;
use App\Models\VamExpressDraft;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VamExpressDraftResource extends Resource
{
    protected static ?string $model = VamExpressDraft::class;

    protected static ?string $slug = 'vam-express-drafts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Черновики ВАМ';

    protected static ?string $modelLabel = 'черновик доставки ВАМ';

    protected static ?string $pluralModelLabel = 'Черновики доставки ВАМ Экспресс';

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
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny() && $record->status === 'draft' && (! $record->order || OrderResource::canEdit($record->order));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['order', 'creator'])->where(fn (Builder $q) => $q->whereNull('order_id')
            ->orWhereIn('order_id', OrderResource::getEloquentQuery()->select('orders.id')));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Group::make(array_merge(VamExpressDraftForm::pricing(), VamExpressDraftForm::details()))->statePath('payload')->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('id')->label('Черновик №')->sortable(),
            TextColumn::make('order.number')->label('Заказ')->searchable()->placeholder('Предварительный подбор'),
            TextColumn::make('quoted_amount')->label('Предварительно')->money('BYN')->placeholder('Не рассчитано'),
            TextColumn::make('quoted_at')->label('Расчёт')->dateTime('d.m.Y H:i'),
            TextColumn::make('creator.name')->label('Создал'),
            TextColumn::make('updated_at')->label('Сохранён')->dateTime('d.m.Y H:i')->sortable(),
        ])->recordActions([EditAction::make()->label('Открыть черновик')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListVamExpressDrafts::route('/'), 'create' => CreateVamExpressDraft::route('/create'), 'edit' => EditVamExpressDraft::route('/{record}/edit')];
    }
}
