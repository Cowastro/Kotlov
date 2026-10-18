<?php

namespace App\Services;

use App\Filament\Resources\Users\UserResource;
use App\Mail\NewB2bAccessRequestMail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class B2bAccessRequestNotifier
{
    public function notify(User $applicant): void
    {
        $adminUrl = UserResource::getUrl('edit', ['record' => $applicant], panel: 'admin');
        $recipients = User::query()
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::make()
                ->title('Новая заявка на B2B-доступ')
                ->body(implode(' · ', array_filter([
                    $applicant->company_name,
                    $applicant->name,
                    $applicant->email,
                    $applicant->phone,
                ])))
                ->icon('heroicon-o-building-office-2')
                ->iconColor('warning')
                ->actions([
                    Action::make('openB2bApplicant')
                        ->label('Проверить партнёра')
                        ->url($adminUrl)
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipients);
        }

        $notificationEmail = trim((string) config('services.b2b.notification_email'));
        if ($notificationEmail === '') {
            return;
        }

        try {
            Mail::to($notificationEmail)->send(new NewB2bAccessRequestMail($applicant));
        } catch (\Throwable $exception) {
            Log::error('B2B access-request email notification failed.', [
                'user_id' => $applicant->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
