<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierApplication extends Model
{
    protected $fillable = [
        'company_name',
        'contact_name',
        'phone',
        'email',
        'website',
        'product_categories',
        'message',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'product_categories' => 'array',
    ];

    public static array $statuses = [
        'new'       => 'Новая',
        'contacted' => 'Связались',
        'approved'  => 'Принята',
    ];

    public static array $categoryLabels = [
        'kotly'           => 'Котлы',
        'teplovye_nasosy' => 'Тепловые насосы',
        'kaminy'          => 'Камины',
        'pechki'          => 'Печи',
        'dymohody'        => 'Дымоходы',
        'vodonagrevateli' => 'Водонагреватели',
        'otoplenie'       => 'Комплектующие отопления',
        'bani'            => 'Товары для бани',
        'other'           => 'Другое',
    ];
}
