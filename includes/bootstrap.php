<?php
declare(strict_types=1);

// Every page starts with: require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/layout.php';

start_session();
verify_csrf();
