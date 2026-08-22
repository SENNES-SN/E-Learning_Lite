<?php

return [
    'moodle_usernames' => array_values(array_filter(array_map(
        fn (string $username): string => strtolower(trim($username)),
        explode(',', (string) env('ADMIN_MOODLE_USERNAMES', ''))
    ))),
];
