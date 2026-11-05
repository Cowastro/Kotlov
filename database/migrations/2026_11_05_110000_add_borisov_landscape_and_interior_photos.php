<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const PHOTOS = [
        'partner-branches/borisov/facade-wide-kotlov-20261105.webp',
        'partner-branches/borisov/interior-stoves-20261105.webp',
        'partner-branches/borisov/interior-chimneys-20261105.webp',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $branches = PartnerBranch::query()
                ->whereHas('city', fn ($query) => $query->where('slug', 'borisov'))
                ->where('address', 'like', '%222%')
                ->lockForUpdate()->get();

            if ($branches->isEmpty()) {
                return;
            }

            if ($branches->count() !== 1) {
                throw new RuntimeException('Expected one Borisov branch at pavilion 222.');
            }

            foreach (self::PHOTOS as $photo) {
                $source = public_path('img/'.$photo);
                if (! is_file($source) || ! Storage::disk('public')->put($photo, file_get_contents($source))) {
                    throw new RuntimeException('Unable to publish Borisov branch photograph.');
                }
            }

            $branch = $branches->sole();
            $gallery = collect(self::PHOTOS)->merge([$branch->hero_image])
                ->merge($branch->gallery ?? [])->filter()->unique()->values()->all();
            $branch->update(['hero_image' => self::PHOTOS[0], 'gallery' => $gallery]);
        });
    }

    public function down(): void
    {
        // Preserve published photographs and subsequent manager edits.
    }
};
