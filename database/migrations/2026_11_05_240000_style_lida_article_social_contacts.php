<?php

use App\Models\BlogPost;
use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $branch = PartnerBranch::where('b2b_client_id', 11)->whereHas('city', fn ($query) => $query->where('slug', 'lida'))->first();
        $post = BlogPost::where('slug', 'pechi-kaminy-dymohody-magazin-lida')->whereHas('city', fn ($query) => $query->where('slug', 'lida'))->first();
        if (! $branch || ! $post) {
            return;
        }
        $contacts = view('partials.branch-social-contacts', ['contactBranch' => $branch])->render();
        $content = preg_replace('~<p><strong>Мессенджеры и соцсети магазина:</strong>.*?</p>~su', $contacts, $post->content, 1);
        if ($content !== $post->content) {
            $post->update(['content' => $content]);
        }
    }

    public function down(): void
    {
        // Keep published contact links.
    }
};
