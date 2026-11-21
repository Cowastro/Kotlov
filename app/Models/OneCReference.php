<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of a 1C list the agent read (organization, warehouse, partner, agreement…), for the selects of the admin. */
class OneCReference extends Model
{
    public $timestamps = false;

    protected $table = 'onec_references';

    protected $fillable = ['kind', 'ref_key', 'description', 'code', 'owner_key', 'inn', 'data', 'deletion_mark', 'is_folder', 'synced_at'];

    protected $casts = [
        'data' => 'array',
        'deletion_mark' => 'boolean',
        'is_folder' => 'boolean',
        'synced_at' => 'datetime',
    ];
}
