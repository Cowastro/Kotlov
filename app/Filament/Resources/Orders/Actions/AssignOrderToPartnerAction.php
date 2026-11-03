<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\PartnerBranch;
use App\Services\Orders\OrderPartnerAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The centre hands a site order to a partner's sales point; points in the customer's city come first. */
class AssignOrderToPartnerAction
{
    public static function make(): Action
    {
        return Action::make('assignToPartner')
            ->label(fn (Order $record): string => $record->partner_branch_id ? 'Передать другому партнёру' : 'Передать партнёру')
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->color('success')
            ->visible(fn (Order $record): bool => ! in_array($record->status, ['cancelled', 'delivered'], true)
                && $record->partner_status !== 'accepted')
            ->modalHeading('Передать заказ партнёру')
            ->modalDescription('Партнёр получит письмо и увидит заказ в B2B-кабинете. Контакты клиента откроются ему после принятия.')
            ->schema([
                Select::make('partner_branch_id')->label('Точка продаж')
                    ->options(fn (Order $record): array => static::options($record))
                    ->searchable()->required(),
                Textarea::make('note')->label('Комментарий партнёру')->rows(3)->maxLength(1000),
            ])
            ->action(function (Order $record, array $data): void {
                try {
                    app(OrderPartnerAssignment::class)->assign(
                        $record, PartnerBranch::query()->with(['city', 'client.user'])->findOrFail($data['partner_branch_id']),
                        auth()->user(), $data['note'] ?? null,
                    );
                    Notification::make()->title('Заказ передан партнёру')->success()->send();
                } catch (ValidationException $exception) {
                    Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                }
            });
    }

    /** @return array<int, string> */
    public static function options(Order $order): array
    {
        $city = Str::lower(trim((string) $order->delivery_city));

        return PartnerBranch::query()->where('is_active', true)
            ->whereHas('client', fn ($client) => $client->where('status', 'approved'))
            ->with('city')->get()
            ->sortBy(fn (PartnerBranch $branch): string => ($city !== '' && Str::contains($city, Str::lower((string) $branch->city?->name)) ? '0' : '1')
                .$branch->city?->name.$branch->name)
            ->mapWithKeys(fn (PartnerBranch $branch): array => [$branch->id => $branch->city?->name.' — '.$branch->name.($branch->address ? ', '.$branch->address : '')])
            ->all();
    }
}
