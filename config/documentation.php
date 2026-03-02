<?php

return [
    'paths' => [
        'product' => base_path('docs/product'),
        'api' => base_path('docs/api'),
        'tooltips' => base_path('docs/tooltips'),
    ],

    'required_front_matter' => [
        'slug',
        'title',
        'summary',
        'section',
        'audience',
        'status',
        'version',
        'tags',
        'owner',
        'route_names',
        'setting_keys',
        'feature_flags',
        'locale',
        'reviewed_at',
    ],

    'front_matter' => [
        'slug_pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        'array_fields' => [
            'tags',
            'route_names',
            'setting_keys',
            'feature_flags',
        ],
    ],

    'allowed_statuses' => [
        'draft',
        'published',
        'deprecated',
    ],

    'locale' => [
        'default' => 'en',
        'allowed' => ['en'],
    ],

    'allowed_severity' => [
        'info',
        'warning',
        'risk',
    ],

    'allowed_domains' => [
        'laravel.com',
        'typesense.org',
        'scramble.dedoc.co',
    ],

    'tooltips' => [
        'short_text_max' => 120,
        'required_fields' => [
            'ui_key',
            'short_text',
            'severity',
            'links',
            'metadata',
        ],
        'required_metadata' => [
            'owner',
            'locale',
        ],
        'ui_key_pattern' => '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
        'allow_relative_links' => true,
    ],

    'sync' => [
        'manifest_path' => env('DOCS_SYNC_MANIFEST_PATH', 'docs-sync/manifest.json'),
        'allowed_modes' => [
            'commit',
            'deploy',
        ],
        'allowed_sources' => [
            'repo',
        ],
        'deploy' => [
            'reindex' => [
                'max_retries' => (int) env('DOCS_DEPLOY_REINDEX_MAX_RETRIES', 3),
                'retry_interval_seconds' => (int) env('DOCS_DEPLOY_REINDEX_RETRY_INTERVAL_SECONDS', 30),
                'timeout_seconds' => (int) env('DOCS_DEPLOY_REINDEX_TIMEOUT_SECONDS', 120),
            ],
        ],
    ],

    'generation' => [
        'output_paths' => [
            'api_route_inventory' => base_path('docs/api/reference/agent-v1-route-inventory.md'),
            'api_surface_reference' => base_path('docs/api/reference/agent-v1-surface-reference.md'),
            'interface_surface_coverage' => base_path('docs/product/interface/surface-coverage.md'),
        ],
    ],

    'search' => [
        'reindex_delay_seconds' => (int) env('DOCS_SEARCH_REINDEX_DELAY_SECONDS', 5),
        'freshness_target_seconds' => 60,
    ],

    'telemetry' => [
        'tooltip_missing_key_dedupe_seconds' => (int) env('DOCS_TELEMETRY_TOOLTIP_DEDUPE_SECONDS', 120),
        'listener_dedupe_seconds' => (int) env('DOCS_TELEMETRY_LISTENER_DEDUPE_SECONDS', 30),
        'recent_failures_max' => (int) env('DOCS_TELEMETRY_RECENT_FAILURES_MAX', 50),
    ],

    'openapi' => [
        'artifact_path' => env('DOCS_OPENAPI_ARTIFACT_PATH', base_path('docs/openapi/messenger.yaml')),
        'linked_doc_slugs_extension' => env('DOCS_OPENAPI_LINKED_SLUGS_EXTENSION', 'x-linked-doc-slugs'),
    ],
];
