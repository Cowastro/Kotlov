<?php

use App\Models\BlogPost;
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
            $table->string('telegram_username', 32)->nullable();
            $table->string('viber_phone', 50)->nullable();
            $table->string('instagram_username', 30)->nullable();
        });

        PartnerBranch::where('b2b_client_id', 11)->whereHas('city', fn ($query) => $query->where('slug', 'lida'))
            ->update(['telegram_username' => 'kotlovlida', 'viber_phone' => '+375 (29) 928-37-66', 'instagram_username' => 'kotlovlida.by']);

        $post = BlogPost::where('slug', 'pechi-kaminy-dymohody-magazin-lida')
            ->whereHas('city', fn ($query) => $query->where('slug', 'lida'))->first();
        if ($post && ! str_contains($post->content, 'https://t.me/kotlovlida')) {
            $contacts = '<p><strong>Мессенджеры и соцсети магазина:</strong><br><a href="https://t.me/kotlovlida" target="_blank" rel="noopener">Telegram: @kotlovlida</a><br><a href="viber://chat?number=%2B375299283766">Viber: +375 (29) 928-37-66</a><br><a href="https://www.instagram.com/kotlovlida.by/" target="_blank" rel="noopener">Instagram: @kotlovlida.by</a></p>';
            $post->update(['content' => str_replace('<p>Продавец: ИП Кузин', $contacts.'<p>Продавец: ИП Кузин', $post->content)]);
        }
        Cache::forget('home:data:v9');
    }

    public function down(): void
    {
        // Preserve published contacts and subsequent partner edits.
    }
};
