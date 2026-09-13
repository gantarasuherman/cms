<?php

return [
    /*
     | Yajra attaches the executed SQL and the raw request to every response
     | while app.debug is on — it reads app.debug directly, so this key cannot
     | turn it off. APP_DEBUG=false in production is what removes it, and that
     | is required anyway: with debug on, Laravel's own error pages leak far
     | more than this does. Verified: the payload disappears once APP_DEBUG=false.
     */

    'search' => [
        'smart' => true,
        'multi_term' => true,
        'case_insensitive' => true,
        'use_wildcards' => false,
        'starts_with' => false,
    ],

    'index_column' => 'DT_RowIndex',

    'engines' => [
        'eloquent' => Yajra\DataTables\EloquentDataTable::class,
        'query' => Yajra\DataTables\QueryDataTable::class,
        'collection' => Yajra\DataTables\CollectionDataTable::class,
        'resource' => Yajra\DataTables\ApiResourceDataTable::class,
    ],

    'builders' => [],

    'nulls_last_sql' => ':column :direction NULLS LAST',

    'error' => null,

    'columns' => [
        'excess' => ['rn', 'row_num'],
        'escape' => '*',
        'raw' => ['action'],
        'blacklist' => ['password', 'remember_token'],
        'whitelist' => '*',
    ],

    'json' => [
        'header' => [],
        'options' => 0,
    ],
];
