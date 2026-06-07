<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallerApplication extends Model
{
    protected $fillable = [
        'contact_name',
        'phone',
        'email',
        'city',
        'company_name',
        'experience_years',
        'specializations',
        'message',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'specializations'  => 'array',
        'experience_years' => 'integer',
    ];

    public static array $statuses = [
        'new'       => 'Новая',
        'contacted' => 'Связались',
        'approved'  => 'Принята',
    ];

    public static array $specializationLabels = [
        'kotly'         => 'Монтаж котлов',
        'teplovye_nasosy' => 'Тепловые насосы',
        'kaminy'        => 'Камины и печи',
        'dymohody'      => 'Дымоходы',
        'otoplenie'     => 'Системы отопления',
        'bani'          => 'Банные печи',
    ];
}
