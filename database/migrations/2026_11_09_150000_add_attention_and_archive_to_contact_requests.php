<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $startedAt = now();

        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->boolean('needs_attention')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->index(['needs_attention', 'archived_at']);
        });

        DB::table('contact_requests')
            ->where(fn ($query) => $query->whereNull('updated_at')->orWhere('updated_at', '<', $startedAt))
            ->update(['needs_attention' => false]);

        DB::table('contact_requests')
            ->whereIn('status', ['new', 'read', 'in_progress', 'waiting'])
            ->whereRaw('coalesce(updated_at, created_at) < ?', [$startedAt->copy()->subDays(30)])
            ->where(fn ($query) => $query->whereNull('next_follow_up_at')->orWhere('next_follow_up_at', '<=', $startedAt))
            ->update(['archived_at' => $startedAt, 'needs_attention' => false]);

        DB::table('contact_requests')->where('external_source', 'site_chat')->whereNull('last_external_event_id')
            ->select('id', 'external_id')->chunkById(100, function ($leads): void {
                foreach ($leads as $lead) {
                    $lastVisitor = DB::table('support_messages')
                        ->join('support_conversations', 'support_conversations.id', '=', 'support_messages.support_conversation_id')
                        ->where('support_conversations.public_id', $lead->external_id)
                        ->where('support_messages.sender_type', 'visitor')->max('support_messages.id');
                    if ($lastVisitor) {
                        DB::table('contact_requests')->where('id', $lead->id)->whereNull('last_external_event_id')
                            ->update(['last_external_event_id' => 'site:'.$lastVisitor]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropIndex(['needs_attention', 'archived_at']);
            $table->dropColumn(['needs_attention', 'archived_at']);
        });
    }
};
