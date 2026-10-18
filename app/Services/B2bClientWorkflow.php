<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Models\B2bClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class B2bClientWorkflow
{
    public function registerApplication(User $user): B2bClient
    {
        return DB::transaction(function () use ($user): B2bClient {
            $client = B2bClient::query()->firstOrNew(['user_id' => $user->id]);
            $client->fill([
                'status' => 'pending',
                'partner_type' => $user->client_type === ClientType::Installer ? 'installer' : 'wholesale',
                'company_name' => $user->company_name ?: $user->name,
                'unp' => $user->company_inn,
                'contact_name' => $user->name,
                'contact_email' => $user->email,
                'contact_phone' => $user->phone,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'application_comment' => $user->b2b_comment,
            ])->save();

            return $client->fresh();
        });
    }

    public function approve(B2bClient $client, ?User $reviewer = null): B2bClient
    {
        return $this->setStatus($client, 'approved', $reviewer);
    }

    public function pause(B2bClient $client, ?User $reviewer = null): B2bClient
    {
        return $this->setStatus($client, 'paused', $reviewer);
    }

    public function reject(B2bClient $client, ?User $reviewer = null): B2bClient
    {
        return $this->setStatus($client, 'rejected', $reviewer);
    }

    private function setStatus(B2bClient $client, string $status, ?User $reviewer): B2bClient
    {
        return DB::transaction(function () use ($client, $status, $reviewer): B2bClient {
            $client->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
            ])->save();

            return $client->fresh();
        });
    }
}
