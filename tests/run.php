<?php
declare(strict_types=1);

// Test suite: php tests/run.php
// Runs every query and rule against an in-memory SQLite copy of the schema.
// No framework, no Composer: each test is a closure, failures are counted.

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/repo.php';
require __DIR__ . '/../includes/validate.php';
require __DIR__ . '/../includes/auth.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $what): void
{
    if (!$condition) {
        throw new RuntimeException($what);
    }
}

function fresh_db(): PDO
{
    $pdo = make_pdo('sqlite::memory:');
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sqlite.sql'));
    return $pdo;
}

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn(fresh_db());
        $passed++;
        echo "  ok    {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  {$name}: {$e->getMessage()}\n";
    }
}

echo "GateArch tests\n";

test('passwords are hashed, never stored in plain text', function (PDO $pdo) {
    create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $u = find_user_by_username($pdo, 'amira');
    check($u['password_hash'] !== 'correct horse battery', 'stored as plain text');
    check(password_verify('correct horse battery', $u['password_hash']), 'hash does not verify');
    check(str_starts_with($u['password_hash'], '$2y$') || str_starts_with($u['password_hash'], '$argon2'), 'unexpected hash format');
});

test('login: right password ok, wrong password and unknown user give the same answer', function (PDO $pdo) {
    create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    [$ok, $user] = attempt_login($pdo, 'amira', 'correct horse battery', '10.0.0.1');
    check($ok === LOGIN_OK && $user['username'] === 'amira', 'valid login failed');
    [$bad] = attempt_login($pdo, 'amira', 'wrong password!!', '10.0.0.1');
    [$unknown] = attempt_login($pdo, 'nobody', 'whatever123', '10.0.0.1');
    check($bad === LOGIN_BAD && $unknown === LOGIN_BAD, 'different results for wrong password and unknown user');
});

test('login throttling: locked after 5 failures, even with the right password', function (PDO $pdo) {
    create_user($pdo, 'liam', 'Liam Chen', 'correct horse battery');
    for ($i = 0; $i < 5; $i++) {
        attempt_login($pdo, 'liam', 'nope-nope-nope', '10.0.0.2');
    }
    [$status] = attempt_login($pdo, 'liam', 'correct horse battery', '10.0.0.2');
    check($status === LOGIN_LOCKED, 'not locked after 5 failures');
});

test('login throttling: old failures expire after the window', function (PDO $pdo) {
    $old = time() - 901;
    for ($i = 0; $i < 5; $i++) {
        record_failure($pdo, 'liam', '10.0.0.3', $old);
    }
    check(recent_failures($pdo, 'liam', '10.0.0.3', 900) === ['user' => 0, 'ip' => 0], 'expired failures still counted');
});

test('login throttling: a success clears the counter', function (PDO $pdo) {
    create_user($pdo, 'zara', 'Zara Ahmed', 'correct horse battery');
    attempt_login($pdo, 'zara', 'wrong-wrong-wrong', '10.0.0.4');
    attempt_login($pdo, 'zara', 'correct horse battery', '10.0.0.4');
    check(recent_failures($pdo, 'zara', '0.0.0.0', 900)['user'] === 0, 'counter not cleared');
});

test('login throttling: one locked account does not lock other users on the same IP', function (PDO $pdo) {
    create_user($pdo, 'liam', 'Liam Chen', 'correct horse battery');
    create_user($pdo, 'noah', 'Noah Bouchard', 'correct horse battery');
    for ($i = 0; $i < 5; $i++) {
        attempt_login($pdo, 'liam', 'nope-nope-nope', '10.0.0.5');
    }
    [$status] = attempt_login($pdo, 'noah', 'correct horse battery', '10.0.0.5');
    check($status === LOGIN_OK, 'shared IP locked out an innocent user');
});

test('login throttling: password spraying from one IP is stopped at 20 failures', function (PDO $pdo) {
    create_user($pdo, 'noah', 'Noah Bouchard', 'correct horse battery');
    for ($i = 0; $i < 20; $i++) {
        attempt_login($pdo, "user{$i}", 'Spring2026!', '10.0.0.6');
    }
    [$status] = attempt_login($pdo, 'noah', 'correct horse battery', '10.0.0.6');
    check($status === LOGIN_LOCKED, 'spraying IP not blocked');
});

test('the dashboard query only returns the student\'s own courses', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $b = create_user($pdo, 'liam', 'Liam Chen', 'correct horse battery');
    $cs = create_course($pdo, 'CS101', 'Programming', 4, '');
    $db = create_course($pdo, 'DB210', 'Databases', 3, '');
    enroll($pdo, $a, $cs);
    enroll($pdo, $b, $db);
    $rows = student_courses($pdo, $a);
    check(count($rows) === 1 && $rows[0]['code'] === 'CS101', 'saw another student\'s course');
    check(student_course($pdo, $a, $db) === null, 'course detail leaks across students');
});

test('a student cannot be enrolled twice in the same course', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $cs = create_course($pdo, 'CS101', 'Programming', 4, '');
    enroll($pdo, $a, $cs);
    try {
        enroll($pdo, $a, $cs);
        check(false, 'duplicate enrollment accepted');
    } catch (PDOException $e) {
        check(is_unique_violation($e), 'wrong error type: ' . $e->getMessage());
    }
});

test('duplicate usernames and course codes are rejected as unique violations', function (PDO $pdo) {
    create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    create_course($pdo, 'CS101', 'Programming', 4, '');
    foreach ([fn () => create_user($pdo, 'amira', 'Other', 'correct horse battery'),
              fn () => create_course($pdo, 'CS101', 'Other', 3, '')] as $fn) {
        try {
            $fn();
            check(false, 'duplicate accepted');
        } catch (PDOException $e) {
            check(is_unique_violation($e), 'not detected as unique violation');
        }
    }
});

test('grades: insert, update and clear keep one row per enrollment', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $e = enroll($pdo, $a, create_course($pdo, 'CS101', 'Programming', 4, ''));
    set_grade($pdo, $e, 71.5);
    set_grade($pdo, $e, 84.25);
    check((int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn() === 1, 'more than one grade row');
    check(grade_of(student_courses($pdo, $a)[0]) === 84.25, 'grade not updated');
    set_grade($pdo, $e, null);
    check(grade_of(student_courses($pdo, $a)[0]) === null, 'grade not cleared');
});

test('the database rejects grades outside 0 to 100', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $e = enroll($pdo, $a, create_course($pdo, 'CS101', 'Programming', 4, ''));
    try {
        set_grade($pdo, $e, 140.0);
        check(false, 'grade 140 accepted');
    } catch (PDOException) {
        check(!$pdo->inTransaction(), 'transaction left open after failure');
    }
});

test('ON DELETE CASCADE: deleting a student removes enrollments and grades', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $e = enroll($pdo, $a, create_course($pdo, 'CS101', 'Programming', 4, ''));
    set_grade($pdo, $e, 90.0);
    delete_user($pdo, $a);
    check((int) $pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn() === 0, 'enrollment left behind');
    check((int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn() === 0, 'grade left behind');
});

test('ON DELETE CASCADE: deleting a course removes its enrollments and grades', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $c = create_course($pdo, 'CS101', 'Programming', 4, '');
    set_grade($pdo, enroll($pdo, $a, $c), 90.0);
    delete_course($pdo, $c);
    check((int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn() === 0, 'grade left behind');
    check(find_user($pdo, $a) !== null, 'student was deleted too');
});

test('delete_user never deletes an admin', function (PDO $pdo) {
    $admin = create_user($pdo, 'root', 'Admin', 'correct horse battery', 'admin');
    delete_user($pdo, $admin);
    check(find_user($pdo, $admin) !== null, 'admin deleted');
});

test('search treats % and _ literally (no wildcard injection)', function (PDO $pdo) {
    create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    create_user($pdo, 'li_am', 'Liam Chen', 'correct horse battery');
    check(count_students($pdo, '%') === 0, '% matched everything');
    check(count_students($pdo, '_') === 1, '_ matched any character');
    check(count_students($pdo, 'KHAN') === 1, 'search is not case-insensitive');
});

test('SQL injection attempts are treated as plain data', function (PDO $pdo) {
    create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    check(find_user_by_username($pdo, "' OR '1'='1") === null, 'injection matched a user');
    check(count_students($pdo, "' OR 1=1 --") === 0, 'injection in search');
    check((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'table changed');
});

test('pagination returns the right slice', function (PDO $pdo) {
    for ($i = 1; $i <= 25; $i++) {
        create_user($pdo, sprintf('student%02d', $i), sprintf('Student %02d', $i), 'correct horse battery');
    }
    check(count(list_students($pdo, '', 20, 0)) === 20, 'first page size');
    $second = list_students($pdo, '', 20, 20);
    check(count($second) === 5 && $second[0]['username'] === 'student21', 'second page slice');
});

test('credit-weighted average ignores ungraded courses', function () {
    $rows = [['grade' => '90.00', 'credits' => 4], ['grade' => '70.00', 'credits' => 2], ['grade' => null, 'credits' => 3]];
    check(weighted_average($rows) === 83.33, 'got ' . var_export(weighted_average($rows), true));
    check(weighted_average([['grade' => null, 'credits' => 3]]) === null, 'should be null with no grades');
});

test('letter grades at the band edges', function () {
    foreach ([[100.0, 'A+'], [90.0, 'A+'], [89.99, 'A'], [80.0, 'A-'], [50.0, 'D-'], [49.99, 'F'], [null, 'Pending']] as [$g, $l]) {
        check(letter_grade($g) === $l, "{$g} gave " . letter_grade($g));
    }
});

test('grade parsing', function () {
    check(parse_grade('') === [null, []], 'empty should clear');
    check(parse_grade(' 84.256 ')[0] === 84.26, 'rounding to 2 decimals');
    check(parse_grade('100')[0] === 100.0, '100 is valid');
    check(parse_grade('-1')[1] !== [], '-1 accepted');
    check(parse_grade('100.01')[1] !== [], '100.01 accepted');
    check(parse_grade('abc')[1] !== [], 'text accepted');
});

test('student and course validation', function () {
    check(validate_student('amira.k', 'Amira Khan', 'long enough pw', true) === [], 'valid student rejected');
    check(validate_student('A', 'x', 'short', true) !== [], 'invalid student accepted');
    check(validate_student('amira.k', 'Amira Khan', '', false) === [], 'empty password should keep the old one on edit');
    check(validate_password(str_repeat('a', 73)) !== [], 'over 72 bytes accepted');
    check(validate_course('CS101', 'Programming', 4) === [], 'valid course rejected');
    check(validate_course('MATH-201', 'Calculus', 3) === [], 'dashed code rejected');
    check(validate_course('cs 101', 'P', 0) !== [], 'invalid course accepted');
});

test('output escaping', function () {
    check(h('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'not escaped');
    check(h("O'Brien") === 'O&#039;Brien', 'single quote not escaped');
    check(h(null) === '', 'null not handled');
});

test('audit log keeps entries newest first', function (PDO $pdo) {
    audit($pdo, 'admin', 'course.create', 'CS101');
    audit($pdo, 'admin', 'course.delete', 'CS101');
    $rows = list_audit($pdo, 10, 0);
    check(count($rows) === 2 && $rows[0]['action'] === 'course.delete', 'wrong order');
});

test('stats count ungraded enrollments', function (PDO $pdo) {
    $a = create_user($pdo, 'amira', 'Amira Khan', 'correct horse battery');
    $c1 = create_course($pdo, 'CS101', 'Programming', 4, '');
    $c2 = create_course($pdo, 'DB210', 'Databases', 3, '');
    set_grade($pdo, enroll($pdo, $a, $c1), 88.0);
    enroll($pdo, $a, $c2);
    $s = stats($pdo);
    check($s === ['students' => 1, 'courses' => 2, 'enrollments' => 2, 'ungraded' => 1], json_encode($s));
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
