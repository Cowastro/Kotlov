<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'message', 'status', 'admin_notes',
    ];

    public static array $statuses = [
        'new'  => 'Новая',
        'read' => 'Прочитана',
        'done' => 'Обработана',
    ];
}
