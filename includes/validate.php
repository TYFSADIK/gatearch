<?php
declare(strict_types=1);

// Server-side validation: the authoritative check behind the JavaScript one.
// Each function returns a list of error messages; an empty list means valid.

function validate_student(string $username, string $fullName, ?string $password, bool $passwordRequired): array
{
    $errors = [];
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        $errors[] = 'Username must be 3 to 40 characters: lowercase letters, digits, dot, dash or underscore.';
    }
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
        $errors[] = 'Full name must be 2 to 120 characters.';
    }
    if ($passwordRequired || ($password !== null && $password !== '')) {
        $errors = [...$errors, ...validate_password($password ?? '')];
    }
    return $errors;
}

function validate_password(string $password): array
{
    if (strlen($password) < 10) {
        return ['Password must be at least 10 characters.'];
    }
    if (strlen($password) > 72) {
        // bcrypt only uses the first 72 bytes.
        return ['Password must be at most 72 characters.'];
    }
    return [];
}

function validate_course(string $code, string $name, ?int $credits): array
{
    $errors = [];
    if (!preg_match('/^[A-Z]{2,6}-?[0-9]{2,4}[A-Z]?$/', $code)) {
        $errors[] = 'Course code looks like CS101 or MATH-201.';
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        $errors[] = 'Course name must be 3 to 120 characters.';
    }
    if ($credits === null || $credits < 1 || $credits > 12) {
        $errors[] = 'Credits must be a whole number from 1 to 12.';
    }
    return $errors;
}

// Returns [grade or null, errors]. An empty field clears the grade.
function parse_grade(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [null, []];
    }
    if (!is_numeric($raw)) {
        return [null, ['Grade must be a number from 0 to 100.']];
    }
    $grade = round((float) $raw, 2);
    if ($grade < 0 || $grade > 100) {
        return [null, ['Grade must be from 0 to 100.']];
    }
    return [$grade, []];
}

function is_unique_violation(PDOException $e): bool
{
    // MySQL 23000 / 1062 and SQLite 23000 / 19 both use SQLSTATE 23000.
    return $e->getCode() === '23000' && stripos($e->getMessage(), 'unique') !== false
        || ($e->errorInfo[1] ?? null) === 1062;
}
