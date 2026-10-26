<?php
declare(strict_types=1);

function config(string $key)
{
    static $config = null;
    $config ??= require __DIR__ . '/config.php';
    return $config[$key];
}

// One shared PDO connection. Errors throw exceptions instead of returning false,
// and real server-side prepared statements are used (no emulation).
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = make_pdo(config('db_dsn'), config('db_user'), config('db_pass'));
    }
    return $pdo;
}

function make_pdo(string $dsn, ?string $user = null, ?string $pass = null): PDO
{
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    if (str_starts_with($dsn, 'sqlite:')) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
