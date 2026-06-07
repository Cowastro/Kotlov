<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    // ===== РЕГИСТРАЦИЯ =====
    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'name.required'      => 'Введите ваше имя.',
            'email.required'     => 'Введите email.',
            'email.email'        => 'Некорректный email адрес.',
            'email.unique'       => 'Этот email уже зарегистрирован.',
            'password.required'  => 'Введите пароль.',
            'password.min'       => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        if ($request->boolean('subscribe')) {
            EmailSubscriber::firstOrCreate(
                ['email' => $user->email],
                ['name' => $user->name, 'is_active' => true, 'confirmed_at' => now()]
            );
        }

        return redirect()->intended('/account')
            ->with('success', 'Добро пожаловать, ' . $user->name . '!');
    }

    // ===== ВХОД =====
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => 'Введите email.',
            'email.email'       => 'Некорректный email адрес.',
            'password.required' => 'Введите пароль.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended('/account')
                ->with('success', 'Добро пожаловать, ' . Auth::user()->name . '!');
        }

        // ВАЖНО: возвращаем _form чтобы модалка знала какую открыть
        return back()
            ->withErrors(['email' => 'Неверный email или пароль.'])
            ->withInput($request->only('email', '_form'));
    }

    // ===== ВЫХОД =====
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Вы вышли из аккаунта.');
    }

    // ===== ЗАБЫЛ ПАРОЛЬ =====
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Введите email.',
            'email.email'    => 'Некорректный email адрес.',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Инструкция по сбросу пароля отправлена на ваш email.');
        }

        return back()->withErrors(['email' => 'Пользователь с таким email не найден.']);
    }

    // ===== ФОРМА СБРОСА ПАРОЛЯ =====
    public function showResetForm(Request $request, string $token)
    {
        return view('pages.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    // ===== СБРОС ПАРОЛЯ =====
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min'       => 'Пароль должен содержать минимум 8 символов.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/')->with('success', 'Пароль успешно изменён. Войдите с новым паролем.');
        }

        return back()->withErrors(['email' => 'Ссылка недействительна или устарела.']);
    }
}