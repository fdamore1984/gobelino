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

    // "Server" key provided by mdmcert.download to sign the APNs push
    // certificate CSR for free (see ApnsController).
    'mdmcert' => [
        'server_key' => env('MDMCERT_SERVER_KEY'),
    ],

    // Our own Android agent APK (Device Owner + polling), used to
    // build the QR provisioning payload in AndroidAgentService.
    'agent' => [
        // e.g. com.gobelino.agent/.receiver.AgentDeviceAdminReceiver
        'admin_component' => env('AGENT_ADMIN_COMPONENT'),
        // Public HTTPS URL where the signed APK is hosted for download
        // during provisioning (must stay reachable and unauthenticated).
        'apk_download_url' => env('AGENT_APK_DOWNLOAD_URL'),
        // SHA-256 of the APK, URL-safe base64 without padding. Update
        // this every time a new APK build is published.
        'apk_checksum' => env('AGENT_APK_CHECKSUM'),
    ],

];
