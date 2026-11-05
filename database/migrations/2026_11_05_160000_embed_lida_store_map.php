<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The partner's own contacts page embeds this exact shop location.
        PartnerBranch::where('b2b_client_id', 11)
            ->whereHas('client', fn ($query) => $query->where('unp', '591355217'))
            ->whereHas('city', fn ($query) => $query->where('slug', 'lida'))
            ->where('address', 'г. Лида, ул. Калинина, 57')
            ->where(fn ($query) => $query->whereNull('map_embed_url')->orWhere('map_embed_url', ''))
            ->update(['map_embed_url' => 'https://www.google.com/maps?q=53.88993402333665,25.316627396308604&hl=ru&z=17&output=embed']);
    }

    public function down(): void
    {
        // Preserve store settings and any subsequent edits.
    }
};
