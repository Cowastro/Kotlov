<?php

namespace App\Http\Controllers;

use App\Services\B2bAccessRequestNotifier;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return to_route('account');
        }

        return view('pages.verify-email', ['user' => $request->user()]);
    }

    public function verify(
        EmailVerificationRequest $request,
        B2bAccessRequestNotifier $b2bNotifier,
    ): RedirectResponse {
        $user = $request->user();
        $wasUnverified = ! $user->hasVerifiedEmail();

        $request->fulfill();

        if ($wasUnverified && $user->b2bClient()->exists()) {
            $b2bNotifier->notify($user->fresh());
        }

        return to_route('account')->with('success', 'Email подтверждён. Аккаунт KOTLOV.BY активирован.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return to_route('account');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::error('Email verification notification failed.', [
                'user_id' => $request->user()->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors(['email' => 'Не удалось отправить письмо. Попробуйте ещё раз через несколько минут.']);
        }

        return back()->with('status', 'Ссылка активации отправлена повторно.');
    }
}
