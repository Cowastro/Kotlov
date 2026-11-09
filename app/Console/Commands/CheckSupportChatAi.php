<?php

namespace App\Console\Commands;

use App\Models\SupportChatSetting;
use App\Services\SupportChatAutoResponder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

class CheckSupportChatAi extends Command
{
    protected $signature = 'support-chat:check-ai';

    protected $description = 'Check the site chat AI provider with a synthetic question; do not create conversations';

    public function handle(SupportChatAutoResponder $responder): int
    {
        $this->line('support_chat_ai_enabled='.(int) SupportChatSetting::aiEnabled());
        $this->line('support_chat_ai_configured='.(int) SupportChatAutoResponder::configured());
        if (! SupportChatAutoResponder::configured()) {
            return self::FAILURE;
        }

        try {
            $responder->checkProvider();
            $this->line('support_chat_ai_responded=1');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line('support_chat_ai_responded=0');
            if ($exception instanceof RequestException) {
                $this->line('support_chat_ai_http_status='.$exception->response->status());
            }

            return self::FAILURE;
        }
    }
}
