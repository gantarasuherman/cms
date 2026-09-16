<?php

/**
 * Chatbot credentials and runtime limits.
 *
 * Tokens live here, read from the environment, for the same reason the social
 * sync tokens do: AuditLogger records the before and after of every settings
 * change, so a token typed into an admin form would be written to the audit
 * table in clear text. Everything an administrator legitimately changes — which
 * channel is on, which flow it runs, how it greets — is a database row.
 */
return [

    'enabled' => (bool) env('BOT_ENABLED', true),

    // Shared secret between Laravel and the Python runtime. The bot service
    // calls back with this in a header; without it the internal API is open to
    // anyone who can reach the container network.
    'internal_token' => env('BOT_INTERNAL_TOKEN'),

    'whatsapp' => [
        // Meta Cloud API. Official, so the number cannot be banned for using an
        // unofficial client, and delivery is not tied to a phone staying online.
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        // Meta echoes this back when registering the webhook.
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        // Every webhook body is signed; an unsigned request is not from Meta.
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        // Bukan rahasia — nomor aplikasi yang tertera terbuka di dasbor Meta.
        // Diperlukan untuk mendaftarkan alamat webhook lewat Graph API, yang
        // membuat alamat tunnel yang berubah-ubah tidak lagi perlu ditempel
        // manusia setiap kali stack dinyalakan.
        'app_id' => env('WHATSAPP_APP_ID'),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Telegram has no signature, so the secret travels in a header it
        // echoes back on every update.
        'secret_token' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'session' => [
        // How long a half-finished conversation waits before it is dropped.
        // Long enough to find a photograph; short enough that tomorrow's
        // "halo" starts a fresh conversation rather than resuming a stale form.
        'timeout_minutes' => (int) env('BOT_SESSION_TIMEOUT', 30),
        // How many invalid answers a node accepts before it follows its
        // configured retry behaviour.
        'max_retries' => (int) env('BOT_MAX_RETRIES', 3),
    ],

    'media' => [
        // Chat media lands on the private disk and is served through a
        // controlled admin route, never a public URL.
        'disk' => 'local',
        'directory' => 'bot',
        'max_bytes' => (int) env('BOT_MEDIA_MAX_BYTES', 10 * 1024 * 1024),
    ],

    'retention' => [
        // Transcripts carry phone numbers and photographs of people's homes.
        // They are pruned; complaints themselves are not.
        'transcript_days' => (int) env('BOT_TRANSCRIPT_RETENTION_DAYS', 180),
    ],

];
