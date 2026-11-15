<?php

return [

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

    'rawijli' => [
        'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),
        'media_disk' => env('MEDIA_DISK', 'public'),
        'marketplace_fee_percent' => (float) env('MARKETPLACE_FEE_PERCENT', 10),
    ],

    'ai' => [
        'driver' => env('AI_PROVIDER', 'auto'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 45),
    ],

    'integrations' => [
        'driver' => env('INTEGRATIONS_DRIVER', 'auto'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/api/connections/oauth/meta/callback'),
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'scopes' => array_filter(explode(',', env('META_SCOPES', 'ads_read,ads_management,business_management,pages_show_list,pages_read_engagement,pages_manage_posts,instagram_basic,instagram_content_publish'))),
    ],

    'tiktok' => [
        'app_id' => env('TIKTOK_APP_ID'),
        'app_secret' => env('TIKTOK_APP_SECRET'),
        'client_key' => env('TIKTOK_CLIENT_KEY', env('TIKTOK_APP_ID')),
        'client_secret' => env('TIKTOK_CLIENT_SECRET', env('TIKTOK_APP_SECRET')),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/api/connections/oauth/tiktok/callback'),
        'content_base' => env('TIKTOK_CONTENT_BASE', 'https://open.tiktokapis.com'),
        'marketing_base' => env('TIKTOK_MARKETING_BASE', 'https://business-api.tiktok.com'),
        'scopes' => array_filter(explode(',', env('TIKTOK_SCOPES', 'user.info.basic,video.publish'))),
    ],

];
