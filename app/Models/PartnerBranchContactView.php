<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerBranchContactView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['partner_branch_id', 'product_id', 'page'];

    public function branch(): BelongsTo { return $this->belongsTo(PartnerBranch::class, 'partner_branch_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
