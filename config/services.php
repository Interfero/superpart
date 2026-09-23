<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides a de facto
    | location for this type of information, allowing packages to have a
    | conventional location to find the various service credentials.
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

    /*
    |--------------------------------------------------------------------------
    | Levelion CRM (интеграция SuperPart)
    |--------------------------------------------------------------------------
    |
    | Значения LEVELION_API_KEY / LEVELION_API_SECRET должны совпадать с
    | SUPERPART_API_KEY / SUPERPART_API_SECRET в CRM.
    |
    */
    'levelion' => [
        // Пока портал на REG: мост promo-agent.ru/sp-lc-gw/lc.
        // После переезда на VPS CRM: https://lead-control.space (или http://127.0.0.1), fallback — мост.
        'base_url' => rtrim((string) env('LEVELION_BASE_URL', ''), '/'),
        'fallback_url' => rtrim((string) env('LEVELION_FALLBACK_URL', ''), '/'),
        'api_key' => env('LEVELION_API_KEY', ''),
        'api_secret' => env('LEVELION_API_SECRET', ''),
        // Предыдущий секрет для ротации без простоя (FR-SYNC-05).
        'api_secret_previous' => env('LEVELION_API_SECRET_PREVIOUS', ''),
        // Сегмент после домена: по умолчанию как в Lead Control (routes/api.php + prefix v1).
        // Не добавляйте сюда «api», если он уже есть в LEVELION_BASE_URL.
        'api_path_prefix' => trim((string) env('LEVELION_API_PATH_PREFIX', 'api/v1'), '/'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'SuperPartPortal/1.0 (https://superpart.ru)'),
    ],

];
