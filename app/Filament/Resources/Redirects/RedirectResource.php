<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Redirects\Pages\ViewRedirect;
use App\Filament\Resources\Redirects\Schemas\RedirectForm;
use App\Filament\Resources\Redirects\Schemas\RedirectInfolist;
use App\Filament\Resources\Redirects\Tables\RedirectsTable;
use App\Models\Redirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTopRightOnSquare;
    protected static ?string $navigationLabel = 'Редиректы';
    protected static ?string $modelLabel = 'Редирект';
    protected static ?string $pluralModelLabel = 'Редиректы';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string { return 'Управление'; }
    public static function form(Schema $schema): Schema { return RedirectForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return RedirectInfolist::configure($schema); }
    public static function table(Table $table): Table { return RedirectsTable::configure($table); }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index'  => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'view'   => ViewRedirect::route('/{record}'),
            'edit'   => EditRedirect::route('/{record}/edit'),
        ];
    }
}