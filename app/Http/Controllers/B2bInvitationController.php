<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Models\User;
use App\Services\B2bTeamService;
use App\Services\NewRegistrationTelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class B2bInvitationController extends Controller
{
    public function show(Request $request, string $token, B2bTeamService $teams): View
    {
        $invitation = $teams->invitation($token);
        $existingUser = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($invitation->email)])->first();

        if ($invitation->isAvailable() && $existingUser && ! $request->user()) {
            $request->session()->put('url.intended', route('b2b.invitation.show', ['token' => $token]));
        }

        return view('pages.b2b.invitation', compact('invitation', 'existingUser', 'token'));
    }

    public function accept(
        Request $request,
        string $token,
        B2bTeamService $teams,
        NewRegistrationTelegramNotifier $registrationNotifier,
    ): RedirectResponse {
        $invitation = $teams->invitation($token);
        if (! $invitation->isAvailable()) {
            return back()->withErrors(['invitation' => 'Приглашение уже использовано, отозвано или просрочено.']);
        }

        $existingUser = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($invitation->email)])->first();
        $created = false;

        if ($existingUser) {
            if (! $request->user()) {
                $request->session()->put('url.intended', route('b2b.invitation.show', ['token' => $token]));

                return to_route('login')->with('status', 'Войдите под email приглашения, затем подтвердите присоединение к компании.');
            }
            $user = $request->user();
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:30'],
                'password' => ['required', 'confirmed', 'min:8'],
                'privacy_accepted' => ['accepted'],
            ], [
                'name.required' => 'Введите имя и фамилию.',
                'phone.required' => 'Введите рабочий телефон.',
                'password.required' => 'Создайте пароль.',
                'password.confirmed' => 'Пароли не совпадают.',
                'password.min' => 'Пароль должен содержать минимум 8 символов.',
                'privacy_accepted.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            ]);

            $user = DB::transaction(function () use ($data, $invitation, $teams): User {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'client',
                    'client_type' => ClientType::Retail,
                    'b2b_approved' => false,
                    'is_active' => true,
                ]);

                $teams->accept($invitation, $user);

                return $user;
            });
            $created = true;
        }

        if (! $created) {
            $teams->accept($invitation, $user);
        }
        Auth::login($user);
        $request->session()->regenerate();

        if ($created) {
            $registrationNotifier->send($user->fresh());
        }

        return to_route('b2b.cabinet')->with('success', 'Приглашение принято. Вы подключены к кабинету '.$invitation->client->company_name.'.');
    }
}
