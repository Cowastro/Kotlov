<?php

namespace App\Http\Controllers;

use App\Models\InstallerApplication;
use App\Models\SupplierApplication;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function storeInstaller(Request $request)
    {
        $data = $request->validate([
            'contact_name'     => 'required|string|max:255',
            'phone'            => 'required|string|max:50',
            'email'            => 'nullable|email|max:255',
            'city'             => 'nullable|string|max:100',
            'company_name'     => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0|max:60',
            'specializations'  => 'nullable|array',
            'specializations.*'=> 'string',
            'message'          => 'nullable|string|max:2000',
        ]);

        InstallerApplication::create($data);

        return back()->with('installer_success', 'Ваша заявка отправлена! Мы свяжемся с вами в течение рабочего дня.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'company_name'       => 'required|string|max:255',
            'contact_name'       => 'required|string|max:255',
            'phone'              => 'required|string|max:50',
            'email'              => 'nullable|email|max:255',
            'website'            => 'nullable|url|max:255',
            'product_categories' => 'nullable|array',
            'product_categories.*' => 'string',
            'message'            => 'nullable|string|max:2000',
        ]);

        SupplierApplication::create($data);

        return back()->with('supplier_success', 'Ваша заявка принята! Менеджер свяжется с вами в течение рабочего дня.');
    }
}
