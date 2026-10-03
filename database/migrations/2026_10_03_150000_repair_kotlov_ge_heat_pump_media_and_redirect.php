<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CANONICAL_PATH = '/teplovyie-nasosyi/kotlov-ge-nl-flm30-100ii-r290-10-kvt';
    private const LEGACY_PATH = '/teplovyie-nasosyi/teplovoy-nasos-flamingo-flm30100-r290';

    public function up(): void
    {
        $galleries = [
            'kotlov-ge-nl-flm30-100ii-r290-10-kvt' => [
                'expected_first' => 'GE_photo.png',
                'images' => [
                    'img/blog/works/ge-r290-heat-pump.png',
                    'img/blog/works/ge-r290-heat-pump-hp3.jpg',
                    'img/blog/works/ge-r290-heat-pump-hp4.jpg',
                ],
            ],
            'kotlov-ge-nl-flm30-130ii-r290-12-8-kvt' => [
                'expected_first' => 'GE_photo.png',
                'images' => [
                    'img/blog/works/ge-r290-heat-pump.png',
                    'img/blog/works/ge-r290-heat-pump-hp3.jpg',
                    'img/blog/works/ge-r290-heat-pump-hp4.jpg',
                ],
            ],
            'kotlov-ge-nl-flm50-160ii-r290-16-kvt' => [
                'expected_first' => 'Flamingo_R290_2.jpg',
                'images' => [
                    'img/blog/works/heatpump-smolevichi-kotlov-ge-r290-cover.jpg',
                    'img/blog/works/heatpump-smolevichi-outdoor-front.jpg',
                    'img/blog/works/heatpump-smolevichi-boiler-room.jpg',
                    'img/blog/works/heatpump-smolevichi-controller.jpg',
                    'img/blog/works/heatpump-smolevichi-hydraulic-tank.jpg',
                    'img/blog/works/heatpump-smolevichi-floor-heating-manifold.jpg',
                ],
            ],
            'kotlov-ge-nl-flm50-190ii-r290-19-kvt' => [
                'expected_first' => 'Flamingo_R290_2.jpg',
                'images' => [
                    'img/blog/works/ge-r290-heat-pump.png',
                    'img/blog/works/ge-r290-heat-pump-hp4.jpg',
                    'img/blog/works/ge-r290-heat-pump-hp3.jpg',
                ],
            ],
        ];

        foreach ($galleries as $slug => $gallery) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'images']);
            if (! $product) {
                continue;
            }

            $currentImages = json_decode((string) $product->images, true);
            $currentFirst = is_array($currentImages) ? ($currentImages[0] ?? null) : null;
            if ($currentFirst !== $gallery['expected_first']) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'images' => json_encode($gallery['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }

        DB::table('redirects')->updateOrInsert(
            ['from_url' => self::LEGACY_PATH],
            [
                'to_url' => self::CANONICAL_PATH,
                'status_code' => 301,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('products')
            ->where('slug', 'teplovoy-nasos-flamingo-flm30100-r290')
            ->update([
                'is_active' => false,
                'is_archived' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('redirects')->where('from_url', self::LEGACY_PATH)->delete();
        // Product images and archive state may be edited later in admin; do not overwrite them.
    }
};
