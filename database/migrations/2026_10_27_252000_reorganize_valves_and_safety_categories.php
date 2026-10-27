<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MIXING_VALVE_SKUS = [
        'PS-011.846',
    ];

    private const RADIATOR_VALVE_SKUS = [
        'PS-004.867',
        'PS-004.868',
        'PS-000.18489',
        'KOTLOV-003150',
        'KOTLOV-003149',
        'KOTLOV-003140',
        'KOTLOV-003131',
        'PS-004.633',
        'KOTLOV-003087',
        'KOTLOV-003088',
        'PS-005.016',
        'PS-004.617',
    ];

    private const REGULATING_VALVE_SKUS = [
        'PS-006.170',
        'PS-004.527',
        'PS-006.308',
        'PS-005.990',
        'PS-005.081',
        'PS-003.963',
        'PS-004.786',
        'PS-004.784',
        'PS-004.785',
        'PS-004.122',
        'PS-006.172',
    ];

    private const BALL_VALVE_SKUS = [
        'PS-004.140',
        'PS-004.779',
        'PS-005.637',
        'PS-006.061',
        'PS-004.783',
        'PS-004.781',
        'PS-004.782',
        'PS-004.778',
        'PS-004.777',
        'PS-004.151',
        'PS-004.775',
        'PS-004.139',
        'PS-004.148',
    ];

    public function up(): void
    {
        $valvesId = $this->categoryId('krany-i-zapornaya-armatura');
        $safetyGroupsId = $this->categoryId('gruppy-bezopasnosti');
        $pipesId = $this->categoryId('truby-i-fitingi');
        $ballValvesId = $this->categoryId('sharovye-krany');
        $mixingId = $this->categoryId('smesitelnye-klapany-i-uzly');
        $radiatorId = $this->categoryId('radiatornaya-armatura');
        $regulatingId = $this->categoryId('predokhranitelnaya-i-reguliruyushchaya-armatura');

        if (! $valvesId || ! $safetyGroupsId || ! $pipesId || ! $ballValvesId || ! $mixingId || ! $radiatorId || ! $regulatingId) {
            return;
        }

        $this->move(self::MIXING_VALVE_SKUS, $valvesId, $mixingId);
        $this->move(self::RADIATOR_VALVE_SKUS, $valvesId, $radiatorId);
        $this->move(self::REGULATING_VALVE_SKUS, $valvesId, $regulatingId);
        $this->move(self::BALL_VALVE_SKUS, $valvesId, $ballValvesId);

        DB::table('categories')->where('id', $valvesId)->update([
            'parent_id' => $pipesId,
            'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', $ballValvesId)->update([
            'parent_id' => $pipesId,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', $safetyGroupsId)->update([
            'parent_id' => $regulatingId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $valvesId = $this->categoryId('krany-i-zapornaya-armatura');
        $safetyGroupsId = $this->categoryId('gruppy-bezopasnosti');
        $pipesId = $this->categoryId('truby-i-fitingi');
        $ballValvesId = $this->categoryId('sharovye-krany');
        $mixingId = $this->categoryId('smesitelnye-klapany-i-uzly');
        $radiatorId = $this->categoryId('radiatornaya-armatura');
        $regulatingId = $this->categoryId('predokhranitelnaya-i-reguliruyushchaya-armatura');

        if (! $valvesId || ! $safetyGroupsId || ! $pipesId || ! $ballValvesId || ! $mixingId || ! $radiatorId || ! $regulatingId) {
            return;
        }

        $this->move(self::MIXING_VALVE_SKUS, $mixingId, $valvesId);
        $this->move(self::RADIATOR_VALVE_SKUS, $radiatorId, $valvesId);
        $this->move(self::REGULATING_VALVE_SKUS, $regulatingId, $valvesId);
        $this->move(self::BALL_VALVE_SKUS, $ballValvesId, $valvesId);

        DB::table('categories')->whereIn('id', [$valvesId, $safetyGroupsId])->update([
            'parent_id' => 0,
            'updated_at' => now(),
        ]);
    }

    private function categoryId(string $slug): ?int
    {
        $id = DB::table('categories')->where('slug', $slug)->value('id');

        return $id ? (int) $id : null;
    }

    /** @param list<string> $skus */
    private function move(array $skus, int $fromCategoryId, int $toCategoryId): void
    {
        DB::table('products')
            ->where('category_id', $fromCategoryId)
            ->whereIn('sku', $skus)
            ->update([
                'category_id' => $toCategoryId,
                'updated_at' => now(),
            ]);
    }
};
