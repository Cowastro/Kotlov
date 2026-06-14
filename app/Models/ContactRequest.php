<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'message', 'status', 'admin_notes',
        'product_id', 'product_name', 'product_url', 'city', 'source',
    ];

    public static array $statuses = [
        'new'  => 'Новая',
        'read' => 'Прочитана',
        'done' => 'Обработана',
    ];
}
