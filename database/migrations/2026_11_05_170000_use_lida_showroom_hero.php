<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $image = 'partner-branches/lida/showroom-20261105.webp';
        if (! Storage::disk('public')->exists($image)) {
            $source = public_path('img/'.$image);
            if (! is_file($source) || ! Storage::disk('public')->put($image, file_get_contents($source))) {
                throw new RuntimeException('Unable to publish the Lida showroom banner.');
            }
        }

        PartnerBranch::where('b2b_client_id', 11)
            ->whereHas('client', fn ($query) => $query->where('unp', '591355217'))
            ->whereHas('city', fn ($query) => $query->where('slug', 'lida'))
            ->where('address', 'г. Лида, ул. Калинина, 57')
            ->update(['hero_image' => $image]);
    }

    public function down(): void
    {
        // Preserve the partner's current image selection.
    }
};
