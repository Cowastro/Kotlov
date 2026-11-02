<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstallerB2bConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Activation link from the "your installer profile now has a B2B cabinet"
 * letter: the partner sets a password on the account they already have and
 * lands in the B2B cabinet. The link is signed, lives 14 days and stops
 * working once a password is set through it.
 */
class B2bActivationController extends Controller
{
    public function show(Request $request, User $user)
    {
        return view('pages.b2b.activation', [
            'user' => $user,
            'valid' => $this->linkIsCurrent($request, $user),
            'client' => $user->activeB2bClient(),
        ]);
    }

    public function activate(Request $request, User $user)
    {
        abort_unless($this->linkIsCurrent($request, $user) && $user->activeB2bClient(), 403);

        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен содержать минимум 8 символов.',
        ]);

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            // The link came to this mailbox, so the address is confirmed.
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('b2b.cabinet')->with('success', 'Пароль сохранён. Добро пожаловать в B2B-кабинет KOTLOV!');
    }

    private function linkIsCurrent(Request $request, User $user): bool
    {
        return $user->is_active
            && hash_equals(InstallerB2bConversion::linkVersion($user), (string) $request->query('v'));
    }
}
