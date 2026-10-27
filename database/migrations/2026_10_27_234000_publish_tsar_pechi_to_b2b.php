<?php

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\TsarPechiB2bOfferPublisher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TsarPechiB2bOfferPublisher::class)->sync();
    }

    public function down(): void
    {
        $source = IntegrationSource::query()->where('code', TsarPechiB2bOfferPublisher::SOURCE_CODE)->first();
        if (! $source) {
            return;
        }

        IntegrationProduct::query()->where('integration_source_id', $source->id)->update([
            'b2b_enabled' => false,
        ]);
        $source->update(['is_active' => false]);
    }
};
