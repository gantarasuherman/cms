<?php

return [
    /*
     | Active CAPTCHA driver.
     |
     |   turnstile - Cloudflare Turnstile. The right answer in production;
     |               needs the two keys below.
     |   math      - a written arithmetic question, verified in session. Works
     |               with no third-party account and stays readable to screen
     |               readers, unlike an image challenge.
     |   null      - no challenge at all. Used by the test suite.
     */
    'driver' => env('CAPTCHA_DRIVER', 'math'),

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
    ],
];

