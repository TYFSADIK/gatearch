<?php
declare(strict_types=1);

// Usage: php bin/seed-demo.php
// Fills an empty database with demo courses, students, enrollments and grades
// for local testing. Every demo account uses the password "demo-password".
// Never run this against a real database.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/repo.php';

$pdo = db();
if (count_courses($pdo) > 0 || count_students($pdo) > 0) {
    fwrite(STDERR, "The database already has data; refusing to seed.\n");
    exit(1);
}

$courses = [
    ['CS101', 'Introduction to Programming', 4, "Variables, control flow, functions and debugging, taught in Python.\nWeekly labs and a final project."],
    ['CS201', 'Data Structures', 4, 'Arrays, linked lists, stacks, queues, hash tables, trees and graphs, with complexity analysis.'],
    ['WEB110', 'Web Foundations', 3, 'Semantic HTML, responsive CSS and accessible JavaScript.'],
    ['DB210', 'Relational Databases', 3, 'Schema design, normalisation, SQL joins, transactions and indexing.'],
    ['NET150', 'Networking Essentials', 3, 'The TCP/IP model, subnetting, routing, DNS and DHCP.'],
    ['SEC220', 'Web Application Security', 3, 'Injection, XSS, CSRF, session security and secure design.'],
];
$courseIds = [];
foreach ($courses as [$code, $name, $credits, $desc]) {
    $courseIds[$code] = create_course($pdo, $code, $name, $credits, $desc);
}

$students = [
    ['amira.k', 'Amira Khan', ['CS101' => 91, 'WEB110' => 84.5, 'DB210' => null]],
    ['liam.c', 'Liam Chen', ['CS101' => 76, 'CS201' => 68.25, 'NET150' => 81]],
    ['sofia.r', 'Sofia Rossi', ['WEB110' => 95, 'SEC220' => 88, 'DB210' => 79.5]],
    ['noah.b', 'Noah Bouchard', ['CS101' => 58, 'NET150' => null]],
    ['zara.a', 'Zara Ahmed', ['CS201' => 89, 'DB210' => 92.75, 'SEC220' => null]],
];
foreach ($students as [$username, $name, $grades]) {
    $id = create_user($pdo, $username, $name, 'demo-password');
    foreach ($grades as $code => $grade) {
        $enrollment = enroll($pdo, $id, $courseIds[$code]);
        if ($grade !== null) {
            set_grade($pdo, $enrollment, (float) $grade);
        }
    }
}
create_user($pdo, 'admin', 'Demo Admin', 'demo-password', 'admin');
audit($pdo, 'cli', 'seed', 'demo data loaded');
echo "Demo data loaded. Log in as admin or amira.k with password demo-password.\n";
