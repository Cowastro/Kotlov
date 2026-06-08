<?php

namespace App\Http\Controllers;

use App\Models\InstallerApplication;
use App\Models\SupplierApplication;
use App\Rules\NoHtmlOrLinks;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function storeInstaller(Request $request)
    {
        $data = $request->validate([
            'contact_name'     => ['required', 'string', 'max:100', new NoHtmlOrLinks()],
            'phone'            => 'required|string|max:30',
            'email'            => 'nullable|email|max:150',
            'city'             => ['nullable', 'string', 'max:100', new NoHtmlOrLinks()],
            'company_name'     => ['nullable', 'string', 'max:255', new NoHtmlOrLinks()],
            'experience_years' => 'nullable|integer|min:0|max:60',
            'specializations'  => 'nullable|array',
            'specializations.*'=> 'string',
            'message'          => ['nullable', 'string', 'max:1000', new NoHtmlOrLinks()],
        ]);

        InstallerApplication::create($data);

        return back()->with('installer_success', 'Ваша заявка отправлена! Мы свяжемся с вами в течение рабочего дня.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'company_name'         => ['required', 'string', 'max:255', new NoHtmlOrLinks()],
            'contact_name'         => ['required', 'string', 'max:100', new NoHtmlOrLinks()],
            'phone'                => 'required|string|max:30',
            'email'                => 'nullable|email|max:150',
            'website'              => 'nullable|url|max:255',
            'product_categories'   => 'nullable|array',
            'product_categories.*' => 'string',
            'message'              => ['nullable', 'string', 'max:1000', new NoHtmlOrLinks()],
        ]);

        SupplierApplication::create($data);

        return back()->with('supplier_success', 'Ваша заявка принята! Менеджер свяжется с вами в течение рабочего дня.');
    }
}
