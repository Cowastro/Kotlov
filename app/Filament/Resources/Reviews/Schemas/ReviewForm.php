<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Автор')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('— Гость —'),

                        TextInput::make('author_name')
                            ->label('Имя автора')
                            ->required(),

                        TextInput::make('author_phone')
                            ->label('Телефон автора'),

                        TextInput::make('author_email')
                            ->label('Email автора')
                            ->email(),
                    ]),

                Section::make('Отзыв')
                    ->columns(2)
                    ->schema([
                        Select::make('reviewable_type')
                            ->label('Тип объекта')
                            ->options([
                                'App\Models\Product'          => 'Товар',
                                'App\Models\InstallerProfile' => 'Монтажник',
                                'App\Models\SupplierProfile'  => 'Поставщик',
                            ])
                            ->required(),

                        TextInput::make('reviewable_id')
                            ->label('ID объекта')
                            ->numeric()
                            ->required()
                            ->suffixAction(
                                Action::make('open_on_site')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->label('На сайте')
                                    ->url(function ($get) {
                                        $type = $get('reviewable_type');
                                        $id   = $get('reviewable_id');
                                        if ($type !== 'App\Models\Product' || !$id) return null;
                                        $product = Product::with('category')->find($id);
                                        if (!$product?->category) return null;
                                        return url('/' . $product->category->slug . '/' . $product->slug . '');
                                    })
                                    ->openUrlInNewTab()
                            ),

                        Placeholder::make('product_title')
                            ->label('Название товара')
                            ->content(function ($get) {
                                $type = $get('reviewable_type');
                                $id   = $get('reviewable_id');
                                if ($type !== 'App\Models\Product' || !$id) return '—';
                                $product = Product::find($id);
                                if (!$product) return '— товар не найден (ID: ' . $id . ') —';
                                return $product->name . ' [' . $product->sku . ']';
                            })
                            ->columnSpanFull(),

                        Select::make('rating')
                            ->label('Рейтинг')
                            ->options([
                                1 => '★ 1 — Плохо',
                                2 => '★★ 2 — Неплохо',
                                3 => '★★★ 3 — Нормально',
                                4 => '★★★★ 4 — Хорошо',
                                5 => '★★★★★ 5 — Отлично',
                            ])
                            ->required(),

                        Toggle::make('is_approved')
                            ->label('Одобрен')
                            ->default(false),

                        Textarea::make('text')
                            ->label('Текст отзыва')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('photos')
                            ->label('Фото к отзыву')
                            ->image()
                            ->multiple()
                            ->directory('reviews')
                            ->columnSpanFull(),
                    ]),

                Section::make('Ответ от магазина')
                    ->schema([
                        Textarea::make('reply')
                            ->label('Текст ответа')
                            ->placeholder('Напишите ответ покупателю...')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('После сохранения ответ появится на сайте под отзывом покупателя.'),

                        DateTimePicker::make('replied_at')
                            ->label('Дата ответа')
                            ->helperText('Заполнится автоматически при добавлении ответа')
                            ->displayFormat('d.m.Y H:i')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn($record) => !$record?->reply),
            ]);
    }
}
