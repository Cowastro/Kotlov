<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone (375XXXXXXXXX) that asked not to receive mailings. */
class CrmMailingOptout extends Model
{
    protected $fillable = ['phone', 'source', 'note'];
}
