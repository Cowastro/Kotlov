<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMailingListMember extends Model
{
    protected $fillable = ['mailing_list_id', 'query', 'counterparty_id', 'b2b_client_id', 'supplier_id', 'name', 'notes'];

    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(CrmMailingList::class, 'mailing_list_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'counterparty_id')->withTrashed();
    }

    public function b2bClient(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
