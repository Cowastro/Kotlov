<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $latestMessage = DB::table('support_messages')
            ->select('body')
            ->whereColumn('support_conversation_id', 'chat.id')
            ->where('sender_type', 'visitor')
            ->orderByDesc('id')
            ->limit(1);

        $chats = DB::table('support_conversations as chat')
            ->selectRaw("'site_chat', chat.public_id, 'site_chat', chat.name, chat.phone")
            ->selectRaw("coalesce((".$latestMessage->toSql()."), '')", $latestMessage->getBindings())
            ->selectRaw("case chat.status when 'closed' then 'done' when 'answered' then 'waiting' else 'new' end")
            ->addSelect('chat.assigned_user_id', 'chat.created_at', 'chat.updated_at')
            ->leftJoin('contact_requests as lead', fn ($join) => $join
                ->on('lead.external_id', '=', 'chat.public_id')
                ->where('lead.external_source', 'site_chat'))
            ->whereNull('lead.id');

        DB::table('contact_requests')->insertUsing([
            'external_source', 'external_id', 'source', 'name', 'phone', 'message', 'status',
            'responsible_user_id', 'created_at', 'updated_at',
        ], $chats);
    }

    public function down(): void
    {
        DB::table('contact_requests')->where('external_source', 'site_chat')->delete();
    }
};
