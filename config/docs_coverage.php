<?php

return [
    'required_locale' => 'en',
    'required_entry_status' => 'published',

    'required_content_markers' => [
        'settings' => ['## Settings', '### Settings'],
        'example' => ['## Example', '### Example'],
        'troubleshooting' => ['## Troubleshooting', '### Troubleshooting'],
    ],

    // Map legacy/renamed keys to canonical keys to avoid false negatives during key migrations.
    'setting_key_aliases' => [
        'dashboard.range_default' => 'dashboard.default_range',
        'jobs.page_size_default' => 'jobs.default_page_size',
    ],

    'critical_routes' => [
        'dashboard',
        'agent.jobs.index',
        'agent.monitor.index',
        'tools.messenger.index',
        'tools.discovery.index',
        'tools.backups.settings',
        'tools.features.settings',
        'tools.memory.index',
        'agent.delegation.index',
        'org.index',
        'profile.show',
        'messenger.link.show',
    ],

    'surfaces' => [
        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'required_routes' => ['dashboard'],
            'required_settings' => ['dashboard.default_range'],
            'required_tooltip_ui_keys' => ['dashboard.overview'],
        ],
        [
            'id' => 'agent_jobs',
            'label' => 'Agent Jobs',
            'required_routes' => ['agent.jobs.index'],
            'required_settings' => ['jobs.default_page_size'],
            'required_tooltip_ui_keys' => ['jobs.overview'],
        ],
        [
            'id' => 'monitor',
            'label' => 'Monitor',
            'required_routes' => ['agent.monitor.index'],
            'required_settings' => ['monitor.poll_interval_seconds'],
            'required_tooltip_ui_keys' => ['monitor.run-states'],
        ],
        [
            'id' => 'messenger_control_plane',
            'label' => 'Messenger Control Plane',
            'required_routes' => ['tools.messenger.index'],
            'required_settings' => ['messenger.health_poll_interval_seconds'],
            'required_tooltip_ui_keys' => ['messenger.control-plane'],
        ],
        [
            'id' => 'requirements_discovery',
            'label' => 'Requirements Discovery',
            'required_routes' => ['tools.discovery.index'],
            'required_settings' => ['discovery.default_provider'],
            'required_tooltip_ui_keys' => ['discovery.sessions'],
        ],
        [
            'id' => 'backups',
            'label' => 'Backups',
            'required_routes' => ['tools.backups.settings'],
            'required_settings' => ['backups.retention_days'],
            'required_tooltip_ui_keys' => ['backups.settings'],
        ],
        [
            'id' => 'feature_flags',
            'label' => 'Feature Flags',
            'required_routes' => ['tools.features.settings'],
            'required_settings' => ['features.flags_refresh_minutes'],
            'required_tooltip_ui_keys' => ['features.settings'],
        ],
        [
            'id' => 'memory_diagnostics',
            'label' => 'Memory Diagnostics',
            'required_routes' => ['tools.memory.index'],
            'required_settings' => ['memory.retrieval_limit'],
            'required_tooltip_ui_keys' => ['memory.diagnostics'],
        ],
        [
            'id' => 'delegation',
            'label' => 'Delegation',
            'required_routes' => ['agent.delegation.index'],
            'required_settings' => ['delegation.max_parallel_tasks'],
            'required_tooltip_ui_keys' => ['delegation.graphs'],
        ],
        [
            'id' => 'org_layer',
            'label' => 'Org Layer',
            'required_routes' => ['org.index'],
            'required_settings' => ['org.default_execution_window_minutes'],
            'required_tooltip_ui_keys' => ['org.overview'],
        ],
        [
            'id' => 'profile_security_account',
            'label' => 'Profile Security Account',
            'required_routes' => ['profile.show'],
            'required_settings' => ['profile.session_timeout_minutes'],
            'required_tooltip_ui_keys' => ['profile.security'],
        ],
        [
            'id' => 'api_token_integration_flows',
            'label' => 'API Token Integration Flows',
            'required_routes' => ['messenger.link.show'],
            'required_settings' => ['api.tokens.default_expiration_days'],
            'required_tooltip_ui_keys' => ['api.tokens', 'integrations.account-link'],
        ],
    ],
];
