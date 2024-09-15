<?php

return [
    'docs' => 'https://devlab.id/docs/',
    'contact' => 'https://devlab.id/docs/contact',
    'feedback_discord_webhook' => env('FEEDBACK_DISCORD_WEBHOOK'),
    'self_hosted' => env('SELF_HOSTED', false),
    'waitlist' => env('WAITLIST', false),
    'license_url' => 'https://licenses.coollabs.io',
    'dev_webhook' => env('SERVEO_URL'),
    'is_windows_docker_desktop' => env('IS_WINDOWS_DOCKER_DESKTOP', false),
    'base_config_path' => env('BASE_CONFIG_PATH', '/data/devlab'),
    'helper_image' => env('HELPER_IMAGE', 'ghcr.io/coollabsio/coolify-helper'),
    'is_horizon_enabled' => env('HORIZON_ENABLED', true),
    'is_scheduler_enabled' => env('SCHEDULER_ENABLED', true),
];
