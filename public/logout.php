<?php
declare(strict_types=1);

// POST only, with a CSRF token (checked in bootstrap), so a link on another
// site cannot log a user out.
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sign_out();
}
redirect('login.php');
