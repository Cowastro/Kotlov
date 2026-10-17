<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicAiSelectionRun extends Model
{
    public const STATUSES = [
        'started' => 'Заполнил запрос',
        'completed' => 'Получил варианты',
        'no_results' => 'Ничего не найдено',
        'product_opened' => 'Открыл товар',
        'lead_captured' => 'Оставил контакт',
        'abandoned' => 'Ушёл без результата',
    ];

    protected $fillable = [
        'user_id',
        'session_hash',
        'intent',
        'page_url',
        'category',
        'catalog_category_id',
        'contact_request_id',
        'selected_product_id',
        'answers',
        'result_product_ids',
        'result_count',
        'status',
        'current_step',
        'recommended_at',
        'lead_prompted_at',
        'contact_submitted_at',
        'product_opened_at',
        'abandoned_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'result_product_ids' => 'array',
        'recommended_at' => 'datetime',
        'lead_prompted_at' => 'datetime',
        'contact_submitted_at' => 'datetime',
        'product_opened_at' => 'datetime',
        'abandoned_at' => 'datetime',
    ];

    public function catalogCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'catalog_category_id');
    }

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function selectedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'selected_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
