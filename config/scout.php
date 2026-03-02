<?php

return [
    'driver' => env('SCOUT_DRIVER', 'null'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', true),

    'after_commit' => true,

    'chunk' => [
        'searchable' => 200,
        'unsearchable' => 200,
    ],

    'soft_delete' => false,

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => (int) env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => (int) env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => (int) env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => (int) env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        'max_total_results' => (int) env('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'import_action' => env('TYPESENSE_IMPORT_ACTION', 'upsert'),
        'model-settings' => [
            App\Models\DocumentationEntry::class => [
                'search-parameters' => [
                    'query_by' => 'title,summary,body,tags,section,route_names,setting_keys',
                ],
            ],
            App\Models\DocumentationFragment::class => [
                'search-parameters' => [
                    'query_by' => 'title,summary,body,section,route_names,setting_keys',
                ],
            ],
            App\Models\ApiDocArtifact::class => [
                'search-parameters' => [
                    'query_by' => 'title,summary,body,tags,section,route_names,setting_keys',
                ],
            ],
        ],
        'index-settings' => [],
    ],

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [],
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [],
    ],
];
