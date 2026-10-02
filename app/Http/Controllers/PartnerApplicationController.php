<?php

namespace App\Http\Controllers;

use App\Models\InstallerApplication;
use App\Models\SupplierApplication;
use App\Rules\NoHtmlOrLinks;
use App\Services\InstallerApplicationTelegramNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartnerApplicationController extends Controller
{
    public function __construct(private InstallerApplicationTelegramNotifier $telegramNotifier) {}

    public function storeInstaller(Request $request)
    {
        $data = Validator::make($request->all(), [
            'contact_name'     => ['required', 'string', 'max:100', new NoHtmlOrLinks()],
            'phone'            => ['required', 'string', 'max:30', new \App\Rules\PhoneNotSpam()],
            'email'            => 'nullable|email|max:150',
            'city'             => ['nullable', 'string', 'max:100', new NoHtmlOrLinks()],
            'company_name'     => ['nullable', 'string', 'max:255', new NoHtmlOrLinks()],
            'experience_years' => 'nullable|integer|min:0|max:60',
            'specializations'  => 'nullable|array',
            'specializations.*'=> 'string',
            'message'          => ['nullable', 'string', 'max:1000', new NoHtmlOrLinks()],
            '_source'          => 'nullable|in:become-installer,installers-catalog,partners,outreach-messenger,category-cta',
        ])->validateWithBag('installer');

        $data['source'] = $data['_source'] ?? 'partners';
        unset($data['_source']);
        $application = InstallerApplication::create($data);
        $this->telegramNotifier->send($application);

        $anchor = match ($request->input('_source')) {
            'become-installer' => route('become-installer') . '#apply',
            'installers-catalog' => route('installers.index') . '#installer-join',
            'outreach-messenger' => route('become-installer', ['ref' => 'messenger']) . '#apply',
            'category-cta' => route('become-installer', ['ref' => 'category']) . '#apply',
            default => route('partners') . '#apply',
        };

        return redirect($anchor)->with('installer_success', 'Ваша заявка отправлена! Мы свяжемся с вами в течение рабочего дня.');
    }

    public function storeSupplier(Request $request)
    {
        $data = Validator::make($request->all(), [
            'company_name'         => ['required', 'string', 'max:255', new NoHtmlOrLinks()],
            'contact_name'         => ['required', 'string', 'max:100', new NoHtmlOrLinks()],
            'phone'                => 'required|string|max:30',
            'email'                => 'nullable|email|max:150',
            'website'              => 'nullable|string|max:255',
            'product_categories'   => 'nullable|array',
            'product_categories.*' => 'string',
            'message'              => ['nullable', 'string', 'max:1000', new NoHtmlOrLinks()],
        ])->validateWithBag('supplier');

        SupplierApplication::create($data);

        $anchor = $request->input('_source') === 'suppliers'
            ? route('suppliers') . '#apply-supplier'
            : route('partners') . '#apply';

        return redirect($anchor)->with('supplier_success', 'Ваша заявка принята! Менеджер свяжется с вами в течение рабочего дня.');
    }
}
