<?php

namespace App\Jobs;

use App\Services\SupportChatAutoResponder;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateSupportChatAiReply
{
    use Dispatchable;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $visitorMessageId,
    ) {}

    public function handle(SupportChatAutoResponder $autoResponder): void
    {
        $autoResponder->reply($this->conversationId, $this->visitorMessageId);
    }
}
