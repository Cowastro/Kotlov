<?php

namespace App\Filament\Resources\B2bClients\RelationManagers;

use App\Filament\Resources\PartnerBranches\PartnerBranchResource;
use App\Models\B2bClient;
use App\Models\PartnerBranch;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Sales points are assigned from the partner's card; only an approved B2B client gets them. */
class PartnerBranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Точки продаж';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->branches()->count();

        return $count ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(PartnerBranchResource::branchFields())->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var B2bClient $client */
        $client = $this->getOwnerRecord();
        $approved = $client->status === 'approved';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('city')
                ->withCount(['contactViews as contact_views_30d_count' => fn (Builder $views) => $views->where('created_at', '>=', now()->subDays(30))]))
            ->description($approved
                ? 'Точки показываются в блоке «Где купить» на карточках товаров выбранного города.'
                : 'Точки скрыты на сайте, пока B2B-доступ партнёра не одобрен.')
            ->columns([
                TextColumn::make('name')->label('Точка продаж')->weight('bold')
                    ->description(fn (PartnerBranch $record): string => (string) $record->address),
                TextColumn::make('city.name')->label('Город'),
                TextColumn::make('phones')->label('Телефоны')->formatStateUsing(fn ($state): string => implode(', ', (array) $state)),
                TextColumn::make('contact_views_30d_count')->label('Показы телефона, 30 дн.')->numeric(),
                IconColumn::make('is_active')->label('На сайте')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Назначить точку продаж')->visible($approved),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading($approved ? 'Точек продаж пока нет' : 'Сначала одобрите B2B-доступ')
            ->emptyStateDescription($approved
                ? 'Назначьте партнёру точку продаж в его городе — она появится в «Где купить».'
                : 'Точку продаж получает только одобренный B2B-клиент.');
    }
}
