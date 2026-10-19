<?php

namespace App\Services\CommercialDocuments;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\CommercialDocument;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class CommercialDocumentControlNotifier
{
    public function notify(): int
    {
        $documents = CommercialDocument::query()
            ->currentRevision()
            ->with('manager')
            ->where(function ($control): void {
                $control->where(function ($followUp): void {
                    $followUp->where('type', 'quote')->whereNotNull('next_follow_up_at')
                        ->where('next_follow_up_at', '<=', now());
                })->orWhere(function ($revision): void {
                    $revision->where('type', 'quote')->where('status', 'needs_revision');
                })->orWhere(function ($sent): void {
                    $sent->where('type', 'quote')->where('status', 'sent')
                        ->whereDate('valid_until', '<=', today()->addDays(3));
                })->orWhere(function ($accepted): void {
                    $accepted->where('type', 'quote')->where('status', 'accepted')->whereDoesntHave('order');
                });
            })
            ->where(function ($fresh): void {
                $fresh->whereNull('last_control_notified_at')
                    ->orWhereDate('last_control_notified_at', '<', today());
            })
            ->orderBy('manager_id')
            ->orderBy('valid_until')
            ->get();

        if ($documents->isEmpty()) {
            return 0;
        }

        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();
        /** @var array<int, array{user: User, documents: Collection<int, CommercialDocument>}> $groups */
        $groups = [];

        foreach ($documents as $document) {
            $targets = $document->manager?->is_active && $document->manager->isManager()
                ? collect([$document->manager])
                : $admins;

            foreach ($targets as $target) {
                $groups[$target->id] ??= ['user' => $target, 'documents' => collect()];
                $groups[$target->id]['documents']->push($document);
            }
        }

        $notifiedDocumentIds = collect();
        foreach ($groups as $group) {
            $controlled = $group['documents']->unique('id')->values();
            $details = $controlled->take(6)->map(
                fn (CommercialDocument $document): string => $document->reference().' — '.$document->controlLabel(),
            )->implode("\n");
            if ($controlled->count() > 6) {
                $details .= "\nЕщё: ".($controlled->count() - 6);
            }

            $notification = Notification::make()
                ->title('Коммерческие предложения требуют действия: '.$controlled->count())
                ->body($details)
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
                ->warning()
                ->actions([
                    Action::make('openCommercialControl')
                        ->label('Открыть очередь')
                        ->url(CommercialDocumentResource::getUrl('index', ['tab' => 'control']))
                        ->markAsRead(),
                ]);

            $group['user']->notifyNow($notification->toDatabase());
            $notifiedDocumentIds->push(...$controlled->pluck('id'));
        }

        $ids = $notifiedDocumentIds->unique()->values();
        if ($ids->isNotEmpty()) {
            CommercialDocument::query()->whereIn('id', $ids)->update(['last_control_notified_at' => now()]);
        }

        return $ids->count();
    }
}
