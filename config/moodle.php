<?php

return [
    'base_url' => env('MOODLE_BASE_URL'),
    'token' => env('MOODLE_TOKEN'),
    'service_name' => env('MOODLE_SERVICE_NAME'),
    'username' => env('MOODLE_USERNAME'),
    'password' => env('MOODLE_PASSWORD'),
    'verify_ssl' => env('MOODLE_VERIFY_SSL', true),
];
