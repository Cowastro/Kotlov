<?php

use App\Services\MembraneTankVolumeFilterSynchronizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MembraneTankVolumeFilterSynchronizer::class)->sync(true);
    }

    public function down(): void
    {
        // The generated attribute is harmless catalogue data. Keep it on rollback
        // to avoid deleting manually reviewed product links.
    }
};
