<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL'),
        'fallback_model' => env('OPENROUTER_FALLBACK_MODEL'),
    ],

    'orga' => [
        'url' => env('ORGA_API_URL'),
        'token' => env('ORGA_API_TOKEN'),
    ],

    'novaterra' => [
        'url' => env('WEB_CUP_API_URL'),
        'key' => env('WEB_CUP_API_KEY'),
    ],

    // F87 : dossier des sauvegardes de la base (par défaut ~/backups en production, storage en local).
    'sauvegardes' => [
        'dossier' => env('BACKUP_DIR'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
