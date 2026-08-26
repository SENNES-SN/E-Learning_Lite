<?php

return [
    'base_url' => env('MOODLE_BASE_URL'),
    'token' => env('MOODLE_TOKEN'),
    'service_name' => env('MOODLE_SERVICE_NAME'),
    'username' => env('MOODLE_USERNAME'),
    'password' => env('MOODLE_PASSWORD'),
    'verify_ssl' => env('MOODLE_VERIFY_SSL', true),
    'dashboard_cache_seconds' => (int) env('MOODLE_DASHBOARD_CACHE_SECONDS', 120),
    'dashboard_cache_stale_seconds' => (int) env('MOODLE_DASHBOARD_CACHE_STALE_SECONDS', 300),
    'notification_cache_seconds' => (int) env('MOODLE_NOTIFICATION_CACHE_SECONDS', 30),
    'notification_cache_stale_seconds' => (int) env('MOODLE_NOTIFICATION_CACHE_STALE_SECONDS', 60),
];
