<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
redirect($user === null ? 'login.php' : ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
