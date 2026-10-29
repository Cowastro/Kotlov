<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Models\EmailSubscriber;
use App\Models\User;
use App\Services\B2bAccessRequestNotifier;
use App\Services\B2bClientWorkflow;
use App\Services\PhoneVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // ===== РЕГИСТРАЦИЯ =====
    public function register(
        Request $request,
        B2bClientWorkflow $b2bWorkflow,
        B2bAccessRequestNotifier $b2bNotifier,
        PhoneVerificationService $phoneVerification,
    )
    {
        // Honeypot: боты заполняют скрытое поле, люди — нет
        if ($request->filled('website')) {
            return redirect('/');
        }

        $request->validate([
            'account_type' => ['required', Rule::in(['legal', 'retail'])],
            'company_name' => ['nullable', 'required_if:account_type,legal', 'string', 'max:255'],
            'company_inn' => ['nullable', 'string', 'max:50'],
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:30'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'privacy_accepted' => ['accepted'],
            'phone_verification_token' => [Rule::requiredIf(config('services.sms_by.enabled')), 'nullable', 'uuid'],
            'phone_code' => [Rule::requiredIf(config('services.sms_by.enabled')), 'nullable', 'digits:6'],
        ], [
            'account_type.required' => 'Выберите тип аккаунта.',
            'account_type.in' => 'Некорректный тип аккаунта.',
            'company_name.required_if' => 'Введите название компании или ИП.',
            'name.required'      => 'Введите ваше имя.',
            'phone.required'     => 'Введите телефон.',
            'email.required'     => 'Введите email.',
            'email.email'        => 'Некорректный email адрес.',
            'email.unique'       => 'Этот email уже зарегистрирован.',
            'password.required'  => 'Введите пароль.',
            'password.min'       => 'Пароль должен содержать минимум 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'privacy_accepted.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            'phone_verification_token.required' => 'Сначала запросите код подтверждения телефона.',
            'phone_code.required' => 'Введите код из SMS.',
            'phone_code.digits' => 'Код должен состоять из шести цифр.',
        ]);

        if (config('services.sms_by.enabled')) {
            $phoneVerification->verifyAndConsume(
                $request->string('phone_verification_token')->toString(),
                $request->string('phone')->toString(),
                $request->string('phone_code')->toString(),
            );
        }

        $isLegal = $request->string('account_type')->toString() === 'legal';
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'phone_verified_at' => config('services.sms_by.enabled') ? now() : null,
            'password' => Hash::make($request->password),
            'client_type' => $isLegal ? ClientType::Wholesale : ClientType::Retail,
            'b2b_approved' => false,
            'company_name' => $isLegal ? $request->company_name : null,
            'company_inn' => $isLegal ? $request->company_inn : null,
        ]);

        if ($isLegal) {
            $b2bWorkflow->registerApplication($user->fresh());
            $b2bNotifier->notify($user->fresh());
        }

        Auth::login($user);

        if ($request->boolean('subscribe')) {
            EmailSubscriber::firstOrCreate(
                ['email' => $user->email],
                ['name' => $user->name, 'is_active' => true, 'confirmed_at' => now()]
            );
        }

        $message = $isLegal
            ? 'Аккаунт создан. Заявка на B2B-доступ отправлена менеджеру.'
            : 'Добро пожаловать, '.$user->name.'!';

        return redirect()->intended('/account')
            ->with('success', $message)
            ->with('open_tab', $isLegal ? 'b2b' : 'orders');
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
