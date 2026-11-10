<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmMailingList extends Model
{
    protected $fillable = ['name', 'notes'];

    public function members(): HasMany
    {
        return $this->hasMany(CrmMailingListMember::class, 'mailing_list_id');
    }
}
