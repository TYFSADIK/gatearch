<?php
declare(strict_types=1);

// Usage: php bin/create-admin.php <username> "<Full Name>"
// The password is read from the GATEARCH_ADMIN_PASSWORD environment variable,
// or asked for on the terminal, so it never lands in shell history.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/repo.php';
require __DIR__ . '/../includes/validate.php';

[$script, $username, $fullName] = $argv + [null, null, null];
if (!$username || !$fullName) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username> \"<Full Name>\"\n");
    exit(1);
}
$password = getenv('GATEARCH_ADMIN_PASSWORD') ?: '';
if ($password === '') {
    echo 'Password (at least 10 characters): ';
    if (stream_isatty(STDIN)) {
        system('stty -echo');
    }
    $password = rtrim((string) fgets(STDIN), "\r\n");
    if (stream_isatty(STDIN)) {
        system('stty echo');
    }
    echo "\n";
}
$errors = validate_student(strtolower($username), $fullName, $password, true);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
create_user(db(), strtolower($username), $fullName, $password, 'admin');
audit(db(), 'cli', 'admin.create', strtolower($username));
echo "Admin {$username} created.\n";
