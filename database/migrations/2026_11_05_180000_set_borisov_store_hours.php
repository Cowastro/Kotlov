<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Store hours confirmed by the owner on 2026-11-05.
        PartnerBranch::whereHas('client', fn ($query) => $query->where('unp', '193265863'))
            ->whereHas('city', fn ($query) => $query->where('slug', 'borisov'))
            ->where('address', 'г. Борисов, ул. Демина, 39, пав. 222')
            ->update(['working_hours' => 'Вт–Пт 09:00–18:00, Сб 09:00–17:00; Вс и Пн — выходные']);
    }

    public function down(): void
    {
        // Preserve confirmed hours and subsequent store edits.
    }
};
