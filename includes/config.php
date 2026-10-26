<?php
declare(strict_types=1);

// Settings come from environment variables (set in docker-compose.yml),
// with defaults that match a local XAMPP or LAMP install.
return [
    'db_dsn'  => getenv('GATEARCH_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=gatearch;charset=utf8mb4',
    'db_user' => getenv('GATEARCH_DB_USER') ?: 'gatearch',
    'db_pass' => getenv('GATEARCH_DB_PASS') ?: '',
    'app_name' => 'GateArch',
    'page_size' => 20,
    // Per username: stops guessing one account. Per IP, higher: stops password
    // spraying without letting one person lock out a whole shared campus network.
    'login_max_failures' => 5,
    'login_max_failures_per_ip' => 20,
    'login_window_seconds' => 900,
];
