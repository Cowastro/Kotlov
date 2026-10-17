<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicAiSelectionRun extends Model
{
    protected $fillable = [
        'user_id',
        'session_hash',
        'intent',
        'category',
        'answers',
        'result_product_ids',
        'status',
    ];

    protected $casts = [
        'answers' => 'array',
        'result_product_ids' => 'array',
    ];
}
