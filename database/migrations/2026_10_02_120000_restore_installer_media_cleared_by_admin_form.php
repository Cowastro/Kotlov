<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreProfileMedia(
            'ns-trade-borisov',
            'img/installers/ns-trade/logo.png',
            [
                'img/installers/ns-trade/01-gas-boiler-room.webp',
                'img/installers/ns-trade/02-dhw-cylinder-piping.webp',
                'img/installers/ns-trade/03-multicircuit-boiler-room.webp',
                'img/installers/ns-trade/04-underfloor-heating-large-room.webp',
                'img/installers/ns-trade/05-pellet-boiler.webp',
                'img/installers/ns-trade/06-solid-fuel-retrofit.webp',
                'img/installers/ns-trade/07-wall-boiler-manifolds.webp',
                'img/installers/ns-trade/08-pool-filtration.webp',
                'img/installers/ns-trade/09-combined-boiler-room.webp',
                'img/installers/ns-trade/10-underfloor-manifold.webp',
            ],
            'img/installers/ns-trade/logo.png',
        );

        $this->restoreProfileMedia(
            'ooo-otoplenie-plius',
            'img/installers/avatars/otoplenie-plus-boiler-room-cover.webp',
            [
                'img/blog/works/heatpump-smolevichi-kotlov-ge-r290-cover.jpg',
                'img/blog/works/heatpump-ostroshitsky-cover.jpg',
                'img/blog/works/kotlov-ge-r32-nareyki-cover.jpg',
                'img/blog/works/heat-pumps-115kw-marina-gorka-cover.jpg',
            ],
        );
    }

    public function down(): void
    {
        // This migration repairs accidentally cleared data. Rolling it back
        // must not remove media that may have since been edited intentionally.
    }

    /**
     * @param  array<string>  $gallery
     */
    private function restoreProfileMedia(
        string $slug,
        string $photo,
        array $gallery,
        ?string $logo = null,
    ): void {
        $profile = DB::table('installer_profiles')->where('slug', $slug)->first();

        if (! $profile) {
            return;
        }

        $updates = [];

        if (blank($profile->photo)) {
            $updates['photo'] = $photo;
        }

        if ($logo !== null && blank($profile->logo)) {
            $updates['logo'] = $logo;
        }

        $currentGallery = json_decode((string) $profile->gallery, true);

        if (! is_array($currentGallery) || $currentGallery === []) {
            $updates['gallery'] = json_encode($gallery, JSON_UNESCAPED_UNICODE);
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            DB::table('installer_profiles')->where('id', $profile->id)->update($updates);
        }
    }
};
