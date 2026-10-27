<?php

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\EcokaminB2bOfferPublisher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(EcokaminB2bOfferPublisher::class)->sync();
    }

    public function down(): void
    {
        $source = IntegrationSource::query()->where('code', EcokaminB2bOfferPublisher::SOURCE_CODE)->first();
        if (! $source) {
            return;
        }

        IntegrationProduct::query()->where('integration_source_id', $source->id)->update([
            'stock_quantity' => 0,
            'b2b_enabled' => false,
        ]);
        $source->update(['is_active' => false]);
    }
};
