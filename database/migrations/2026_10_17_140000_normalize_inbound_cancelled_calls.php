<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('telephony_calls')
            ->where('direction', 'inbound')
            ->where('status', 'cancelled')
            ->update(['status' => 'missed']);
    }

    public function down(): void
    {
        // The original provider status cannot be reconstructed reliably.
    }
};
