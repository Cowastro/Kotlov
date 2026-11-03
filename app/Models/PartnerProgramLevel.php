<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerProgramLevel extends Model
{
    public const METRICS = [
        'revenue' => 'Оплаченный оборот, BYN',
        'orders' => 'Оплаченные заказы, шт.',
    ];

    protected $fillable = ['rank', 'name', 'is_active', 'benefits', 'installer_benefits', 'rules'];

    protected $casts = ['rules' => 'array', 'is_active' => 'boolean', 'rank' => 'integer'];
}
