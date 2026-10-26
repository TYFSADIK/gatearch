<?php
declare(strict_types=1);

// Every database query in the app lives in this file. All of them use prepared
// statements with bound parameters; no user input is ever concatenated into SQL.

// ---------- users ----------

function find_user_by_username(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, full_name, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch() ?: null;
}

function find_user(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, full_name, role, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_user(PDO $pdo, string $username, string $fullName, string $password, string $role = 'student'): int
{
    $stmt = $pdo->prepare('INSERT INTO users (username, full_name, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT), $role, now()]);
    return (int) $pdo->lastInsertId();
}

function update_user(PDO $pdo, int $id, string $username, string $fullName): void
{
    $stmt = $pdo->prepare('UPDATE users SET username = ?, full_name = ? WHERE id = ?');
    $stmt->execute([$username, $fullName, $id]);
}

function set_password(PDO $pdo, int $id, string $password): void
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
}

// Re-hash on login when PHP's default algorithm or cost has changed.
function rehash_if_needed(PDO $pdo, array $user, string $password): void
{
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        set_password($pdo, (int) $user['id'], $password);
    }
}

function delete_user(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$id]);
}

function like_pattern(string $q): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
}

function list_students(PDO $pdo, string $q, int $limit, int $offset): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.full_name, u.created_at,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id) AS course_count
           FROM users u
          WHERE u.role = 'student' AND (u.username LIKE :q ESCAPE '!' OR u.full_name LIKE :q2 ESCAPE '!')
          ORDER BY u.full_name, u.username
          LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':q', like_pattern($q));
    $stmt->bindValue(':q2', like_pattern($q));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_students(PDO $pdo, string $q = ''): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND (username LIKE ? ESCAPE '!' OR full_name LIKE ? ESCAPE '!')");
    $stmt->execute([like_pattern($q), like_pattern($q)]);
    return (int) $stmt->fetchColumn();
}

function all_students(PDO $pdo): array
{
    return $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();
}

// ---------- courses ----------

function find_course(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, code, name, credits, description FROM courses WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_course(PDO $pdo, string $code, string $name, int $credits, string $description): int
{
    $stmt = $pdo->prepare('INSERT INTO courses (code, name, credits, description) VALUES (?, ?, ?, ?)');
    $stmt->execute([$code, $name, $credits, $description]);
    return (int) $pdo->lastInsertId();
}

function update_course(PDO $pdo, int $id, string $code, string $name, int $credits, string $description): void
{
    $stmt = $pdo->prepare('UPDATE courses SET code = ?, name = ?, credits = ?, description = ? WHERE id = ?');
    $stmt->execute([$code, $name, $credits, $description, $id]);
}

function delete_course(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->execute([$id]);
}

function list_courses(PDO $pdo, string $q, int $limit, int $offset): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.code, c.name, c.credits, c.description,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
           FROM courses c
          WHERE c.code LIKE :q ESCAPE '!' OR c.name LIKE :q2 ESCAPE '!'
          ORDER BY c.code
          LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':q', like_pattern($q));
    $stmt->bindValue(':q2', like_pattern($q));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_courses(PDO $pdo, string $q = ''): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE code LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!'");
    $stmt->execute([like_pattern($q), like_pattern($q)]);
    return (int) $stmt->fetchColumn();
}

function all_courses(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, name, credits FROM courses ORDER BY code')->fetchAll();
}

// ---------- enrollments and grades ----------

function enroll(PDO $pdo, int $studentId, int $courseId): int
{
    $stmt = $pdo->prepare('INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, ?)');
    $stmt->execute([$studentId, $courseId, now()]);
    return (int) $pdo->lastInsertId();
}

function unenroll(PDO $pdo, int $enrollmentId): void
{
    $stmt = $pdo->prepare('DELETE FROM enrollments WHERE id = ?');
    $stmt->execute([$enrollmentId]);
}

function find_enrollment(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT e.id, u.username, u.full_name, c.code, c.name
           FROM enrollments e
           JOIN users u   ON u.id = e.student_id
           JOIN courses c ON c.id = e.course_id
          WHERE e.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Insert or update the single grade row for an enrollment; null clears it.
// Written as select-then-write inside a transaction so it runs the same on MySQL and SQLite.
function set_grade(PDO $pdo, int $enrollmentId, ?float $grade): void
{
    $pdo->beginTransaction();
    try {
        if ($grade === null) {
            $pdo->prepare('DELETE FROM grades WHERE enrollment_id = ?')->execute([$enrollmentId]);
        } else {
            $exists = $pdo->prepare('SELECT id FROM grades WHERE enrollment_id = ?');
            $exists->execute([$enrollmentId]);
            if ($exists->fetchColumn()) {
                $pdo->prepare('UPDATE grades SET grade = ?, updated_at = ? WHERE enrollment_id = ?')
                    ->execute([$grade, now(), $enrollmentId]);
            } else {
                $pdo->prepare('INSERT INTO grades (enrollment_id, grade, updated_at) VALUES (?, ?, ?)')
                    ->execute([$enrollmentId, $grade, now()]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function list_enrollments(PDO $pdo, ?int $courseId = null): array
{
    $sql = 'SELECT e.id, e.enrolled_at, u.id AS student_id, u.username, u.full_name,
                   c.id AS course_id, c.code, c.name, g.grade
              FROM enrollments e
              JOIN users u   ON u.id = e.student_id
              JOIN courses c ON c.id = e.course_id
         LEFT JOIN grades g  ON g.enrollment_id = e.id';
    $params = [];
    if ($courseId !== null) {
        $sql .= ' WHERE e.course_id = ?';
        $params[] = $courseId;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY c.code, u.full_name');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// The student dashboard query: scoped by the session user ID, so a student
// can only ever read their own rows.
function student_courses(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.code, c.name, c.credits, g.grade, g.updated_at
           FROM enrollments e
           JOIN courses c ON c.id = e.course_id
      LEFT JOIN grades g  ON g.enrollment_id = e.id
          WHERE e.student_id = ?
          ORDER BY c.code'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function student_course(PDO $pdo, int $studentId, int $courseId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.code, c.name, c.credits, c.description, e.enrolled_at, g.grade, g.updated_at
           FROM enrollments e
           JOIN courses c ON c.id = e.course_id
      LEFT JOIN grades g  ON g.enrollment_id = e.id
          WHERE e.student_id = ? AND e.course_id = ?'
    );
    $stmt->execute([$studentId, $courseId]);
    return $stmt->fetch() ?: null;
}

// ---------- grading helpers (pure) ----------

// MySQL returns DECIMAL as a string ("85.50"), SQLite as a number; normalise both.
function grade_of(array $row): ?float
{
    return $row['grade'] === null ? null : (float) $row['grade'];
}

// Credit-weighted average of the graded courses only; null when nothing is graded yet.
function weighted_average(array $rows): ?float
{
    $points = 0.0;
    $credits = 0;
    foreach ($rows as $row) {
        $grade = grade_of($row);
        if ($grade === null) {
            continue;
        }
        $points += $grade * (int) $row['credits'];
        $credits += (int) $row['credits'];
    }
    return $credits > 0 ? round($points / $credits, 2) : null;
}

function letter_grade(?float $grade): string
{
    if ($grade === null) {
        return 'Pending';
    }
    foreach ([[90, 'A+'], [85, 'A'], [80, 'A-'], [77, 'B+'], [73, 'B'], [70, 'B-'],
              [67, 'C+'], [63, 'C'], [60, 'C-'], [57, 'D+'], [53, 'D'], [50, 'D-']] as [$min, $letter]) {
        if ($grade >= $min) {
            return $letter;
        }
    }
    return 'F';
}

// ---------- login throttling ----------

// Failed logins in the window, counted separately per username and per IP.
// Returns ['user' => n, 'ip' => n].
function recent_failures(PDO $pdo, string $username, string $ip, int $windowSeconds, ?int $now = null): array
{
    $since = ($now ?? time()) - $windowSeconds;
    $stmt = $pdo->prepare(
        'SELECT SUM(CASE WHEN username = ? THEN 1 ELSE 0 END) AS by_user,
                SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END)       AS by_ip
           FROM login_attempts
          WHERE attempted_at > ? AND (username = ? OR ip = ?)'
    );
    $stmt->execute([$username, $ip, $since, $username, $ip]);
    $row = $stmt->fetch();
    return ['user' => (int) ($row['by_user'] ?? 0), 'ip' => (int) ($row['by_ip'] ?? 0)];
}

function record_failure(PDO $pdo, string $username, string $ip, ?int $now = null): void
{
    $pdo->prepare('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)')
        ->execute([$username, $ip, $now ?? time()]);
}

function clear_failures(PDO $pdo, string $username): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$username]);
}

// ---------- audit log and stats ----------

function audit(PDO $pdo, string $actor, string $action, string $detail): void
{
    $pdo->prepare('INSERT INTO audit_log (actor, action, detail, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$actor, $action, mb_substr($detail, 0, 255), now()]);
}

function list_audit(PDO $pdo, int $limit, int $offset): array
{
    $stmt = $pdo->prepare('SELECT actor, action, detail, created_at FROM audit_log ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_audit(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
}

function stats(PDO $pdo): array
{
    return [
        'students'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'courses'     => (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
        'enrollments' => (int) $pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn(),
        'ungraded'    => (int) $pdo->query('SELECT COUNT(*) FROM enrollments e LEFT JOIN grades g ON g.enrollment_id = e.id WHERE g.id IS NULL')->fetchColumn(),
    ];
}
