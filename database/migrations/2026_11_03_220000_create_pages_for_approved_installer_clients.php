<?php

use App\Models\B2bClient;
use App\Services\InstallerPageProvisioner;
use Illuminate\Database\Migrations\Migration;

/** Approved installer B2B clients without an installer page get one to fill in the cabinet. */
return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(InstallerPageProvisioner::class);
        B2bClient::query()->where('status', 'approved')->where('partner_type', 'installer')
            ->whereNotNull('user_id')
            ->eachById(fn (B2bClient $client) => $provisioner->ensureFor($client));
    }

    public function down(): void
    {
        // Pages may already be filled by partners; nothing is removed.
    }
};
