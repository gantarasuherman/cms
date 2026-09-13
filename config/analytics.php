<?php

return [
    /*
     | Visitor statistics. Turning this off stops all recording; the dashboard
     | then says so rather than showing zeroes that look like a dead site.
     */
    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
     | How long individual page-view rows are kept. Aggregates are computed on
     | read, so pruning only costs history depth, never today's numbers.
     | `visitors:prune` enforces this; it is scheduled daily.
     */
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 365),

    /*
     | Requests whose user agent contains any of these are not recorded. The
     | list is deliberately short and lowercase-matched: the goal is to keep
     | obvious crawlers out of the counts, not to win an arms race.
     */
    'bot_signatures' => [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'ia_archiver',
        'curl', 'wget', 'python-requests', 'headlesschrome', 'lighthouse',
        'pingdom', 'uptimerobot', 'monitoring', 'preview', 'scraper',
    ],

    /*
     | Paths never recorded, matched with str-is patterns.
     */
    'ignore_paths' => [
        'admin', 'admin/*', 'api/*', 'storage/*', 'build/*',
        'sitemap.xml', 'robots.txt', 'up',
    ],
];

