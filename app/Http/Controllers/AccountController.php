<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $orders = $user->orders()
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('pages.account', compact('user', 'orders'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Введите имя.',
        ]);

        $user->update($request->only([
            'name',
            'phone',
        ]));

        return back()
            ->with('success', 'Профиль обновлён.')
            ->with('open_tab', 'profile');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', 'min:8'],
        ], [
            'current_password.required' => 'Введите текущий пароль.',
            'password.required'         => 'Введите новый пароль.',
            'password.confirmed'        => 'Пароли не совпадают.',
            'password.min'              => 'Пароль должен содержать минимум 8 символов.',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Неверный текущий пароль.'])
                ->with('open_tab', 'security');
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()
            ->with('success', 'Пароль успешно изменён.')
            ->with('open_tab', 'security');
    }

    public function b2bRequest(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'client_type' => [
                'required',
                Rule::in([
                    ClientType::Wholesale->value,
                    ClientType::Installer->value,
                ]),
            ],
            'company_name' => ['required', 'string', 'max:255'],
            'company_inn'  => ['nullable', 'string', 'max:50'],
        ], [
            'client_type.required'  => 'Выберите тип B2B-аккаунта.',
            'client_type.in'        => 'Некорректный тип B2B-аккаунта.',
            'company_name.required' => 'Введите название компании.',
        ]);

        $user->update([
            'client_type'  => ClientType::from($request->client_type),
            'b2b_approved' => false,
            'company_name' => $request->company_name,
            'company_inn'  => $request->company_inn,
        ]);

        return back()
            ->with('success', 'Заявка на B2B-доступ отправлена. После проверки администратор откроет специальные условия.')
            ->with('open_tab', 'b2b');
    }
}