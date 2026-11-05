<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->string('whatsapp_phone', 50)->nullable();
        });
        // Public messenger contacts from sbg.by/contacts; Instagram confirmed by owner.
        PartnerBranch::whereHas('city', fn ($query) => $query->where('slug', 'minsk'))
            ->whereHas('client', fn ($query) => $query->where('unp', '193265863'))
            ->update(['telegram_username' => 'sbgopt_bot', 'viber_phone' => '+375 (29) 632-23-11',
                'whatsapp_phone' => '+375 (29) 632-23-11', 'instagram_username' => 'pechkin_shop']);
        Cache::forget('home:data:v9');
    }

    public function down(): void
    {
        // Preserve published partner contacts.
    }
};
