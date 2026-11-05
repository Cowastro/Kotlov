<?php

use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $branches = PartnerBranch::query()
                ->whereHas('city', fn ($query) => $query->where('slug', 'borisov'))
                ->where('address', 'like', '%222%')->lockForUpdate()->get();
            if ($branches->isEmpty()) {
                return;
            }
            if ($branches->count() !== 1) {
                throw new RuntimeException('Expected one Borisov branch at pavilion 222.');
            }
            $old = 'partner-branches/borisov/facade-wide-kotlov-20261105.webp';
            $new = 'partner-branches/borisov/facade-original-logo-20261105.webp';
            if (! Storage::disk('public')->put($new, file_get_contents(public_path('img/'.$new)))) {
                throw new RuntimeException('Unable to publish corrected branch photograph.');
            }
            $branch = $branches->sole();
            $gallery = collect($branch->gallery ?? [])->map(fn ($path) => $path === $old ? $new : $path)
                ->prepend($new)->unique()->values()->all();
            $branch->update([
                'hero_image' => $branch->hero_image === $old ? $new : $branch->hero_image,
                'gallery' => $gallery,
            ]);
        });
    }

    public function down(): void
    {
        // Preserve published assets and subsequent manager changes.
    }
};
