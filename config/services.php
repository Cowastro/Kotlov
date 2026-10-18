<?php

return [
    'belarus_taxpayer_registry' => [
        'url' => env('BELARUS_TAXPAYER_REGISTRY_URL', 'http://grp.nalog.gov.by/api/grp-public/data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'enabled' => env('FORM_TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    'b2b' => [
        'notification_email' => env('B2B_NOTIFICATION_EMAIL', 'order@kotlov.by'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'allow_in_tests' => (bool) env('TELEGRAM_ALLOW_IN_TESTS', false),
        'orders_chat_id' => env('TELEGRAM_ORDERS_CHAT_ID'),
        'contact_requests_chat_id' => env('TELEGRAM_CONTACT_REQUESTS_CHAT_ID', env('TELEGRAM_ORDERS_CHAT_ID')),
        'support_chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID', env('TELEGRAM_CONTACT_REQUESTS_CHAT_ID', env('TELEGRAM_ORDERS_CHAT_ID'))),
        'telephony_chat_id' => env('TELEGRAM_TELEPHONY_CHAT_ID', env('TELEGRAM_ORDERS_CHAT_ID')),
        'mail_chat_id' => env('TELEGRAM_MAIL_CHAT_ID', env('TELEGRAM_ORDERS_CHAT_ID')),
        'installer_applications_chat_id' => env('TELEGRAM_INSTALLER_APPLICATIONS_CHAT_ID', env('TELEGRAM_ORDERS_CHAT_ID')),
    ],

    'ai' => [
        'anthropic_key' => env('ANTHROPIC_API_KEY', ''),
        'api_key' => env('AI_API_KEY', ''),
        'api_url' => env('AI_API_URL', ''),
        'model' => env('AI_MODEL', 'deepseek-chat'),
        'support_chat_timeout' => (int) env('AI_SUPPORT_CHAT_TIMEOUT', 12),
        'match_model' => env('AI_MATCH_MODEL', ''),
        'openai_key' => env('OPENAI_API_KEY', ''),
        'openai_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4.1'),
        'openai_responses_url' => env('OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses'),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'deepseek_document_model' => env('DEEPSEEK_DOCUMENT_MODEL', 'deepseek-flash'),
        'openai_research_model' => env('OPENAI_RESEARCH_MODEL', 'gpt-6.1-sol'),
        'openai_research_timeout' => env('OPENAI_RESEARCH_TIMEOUT', 180),
        'openai_proxy' => env('OPENAI_PROXY', ''),
        'market_research_provider' => env('MARKET_RESEARCH_PROVIDER', env('MARKET_OPENAI_PROVIDER', 'deepseek')),
        'market_openai_provider' => env('MARKET_OPENAI_PROVIDER', 'deepseek'),
        'deepseek_anthropic_url' => env('DEEPSEEK_ANTHROPIC_URL', 'https://api.deepseek.com/anthropic/v1/messages'),
        'deepseek_research_model' => env('DEEPSEEK_RESEARCH_MODEL', 'deepseek-v4-pro'),
        'deepseek_research_fallback_model' => env('DEEPSEEK_RESEARCH_FALLBACK_MODEL', 'deepseek-flash'),
        'market_research_safe_mode' => (bool) env('MARKET_RESEARCH_SAFE_MODE', true),
        'market_research_max_search_uses' => (int) env('MARKET_RESEARCH_MAX_SEARCH_USES', 6),
        'market_research_max_output_tokens' => (int) env('MARKET_RESEARCH_MAX_OUTPUT_TOKENS', 8000),
        'market_research_cache_minutes' => (int) env('MARKET_RESEARCH_CACHE_MINUTES', 360),
        'azure_openai_key' => env('AZURE_OPENAI_API_KEY', ''),
        'azure_openai_responses_url' => env('AZURE_OPENAI_RESPONSES_URL', ''),
        'azure_openai_research_model' => env('AZURE_OPENAI_RESEARCH_MODEL', 'gpt-5.5'),
    ],

    'belarus_company_registry' => [
        'url' => env('BELARUS_COMPANY_REGISTRY_URL', 'https://api.contragento.by/api/check'),
        'username' => env('BELARUS_COMPANY_REGISTRY_USERNAME', ''),
        'password' => env('BELARUS_COMPANY_REGISTRY_PASSWORD', ''),
    ],

];
