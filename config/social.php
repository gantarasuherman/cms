<?php

/**
 * Credentials for reading engagement figures back from the platforms.
 *
 * These are secrets, so they live in the environment rather than in the
 * settings table: `AuditLogger` records the before and after of every settings
 * change, which would write an access token into the audit log in clear text.
 * Everything that is not a secret — which platforms to sync, whether a post
 * syncs at all — stays editable in the admin panel.
 */
return [

    // Master switch. With this off nothing reaches out, whatever is configured.
    'sync_enabled' => (bool) env('SOCIAL_SYNC_ENABLED', true),

    'timeout' => (int) env('SOCIAL_SYNC_TIMEOUT', 8),

    'instagram' => [
        // Instagram Graph API. Needs a Business or Creator account linked to a
        // Facebook Page, and a long-lived Page token with instagram_basic and
        // pages_read_engagement. The Basic Display API is not an alternative:
        // it was shut down in December 2024 and never exposed like counts.
        'user_id' => env('INSTAGRAM_USER_ID'),
        'token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'version' => env('INSTAGRAM_GRAPH_VERSION', 'v21.0'),

        // Official oEmbed, the only supported way to read a post belonging to
        // somebody else. Shaped `{app-id}|{client-token}`; needs the oEmbed
        // Read feature on the Meta app. It returns the account name and one
        // preview frame — never like or comment counts, which Meta removed
        // from oEmbed in October 2020.
        'oembed_token' => env('INSTAGRAM_OEMBED_TOKEN'),

        // Preview the top comments under the caption. Off by default: it costs
        // a second request per post per sync, and needs
        // instagram_manage_comments, which most tokens are not granted. With
        // it off the comment *count* still syncs — only the preview is absent.
        'read_comments' => (bool) env('INSTAGRAM_READ_COMMENTS', false),
    ],

    'facebook' => [
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'token' => env('FACEBOOK_PAGE_TOKEN'),
        'version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
    ],

    'youtube' => [
        // The one that needs no OAuth: a server API key is enough.
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

];
