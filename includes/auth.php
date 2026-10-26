<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repo.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('gatearch');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    return isset($_SESSION['user_id'])
        ? ['id' => (int) $_SESSION['user_id'], 'role' => $_SESSION['role'], 'username' => $_SESSION['username'], 'name' => $_SESSION['name']]
        : null;
}

// The role guard: one line at the top of every protected page.
//   require_role('admin');   require_role('student');
// Not logged in goes to the login page; the wrong role gets a 403.
function require_role(string $role): array
{
    start_session();
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('403: this page is not available for your account.');
    }
    return $user;
}

// ---------- CSRF ----------

// One token per session (not per page load), so several open tabs keep working.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        exit('403: the form expired. Go back, reload the page and try again.');
    }
}

// ---------- login and logout ----------

const LOGIN_OK = 'ok';
const LOGIN_BAD = 'bad';
const LOGIN_LOCKED = 'locked';

// Returns [status, user]. The same message is shown for an unknown username and a
// wrong password, so the form does not reveal which accounts exist.
function attempt_login(PDO $pdo, string $username, string $password, string $ip): array
{
    $failures = recent_failures($pdo, $username, $ip, config('login_window_seconds'));
    if ($failures['user'] >= config('login_max_failures') || $failures['ip'] >= config('login_max_failures_per_ip')) {
        return [LOGIN_LOCKED, null];
    }
    $user = find_user_by_username($pdo, $username);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        record_failure($pdo, $username, $ip);
        return [LOGIN_BAD, null];
    }
    clear_failures($pdo, $username);
    rehash_if_needed($pdo, $user, $password);
    return [LOGIN_OK, $user];
}

function sign_in(array $user): void
{
    // New session ID after the privilege change, against session fixation.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['full_name'];
    unset($_SESSION['csrf']);
}

function sign_out(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax']);
    }
    session_destroy();
}

// ---------- flash messages (shown once after a redirect) ----------

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------- input helpers ----------

function post_string(string $key, int $max = 255): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

function post_int(string $key): ?int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false ? null : $value;
}

function query_int(string $key, int $default = 0): int
{
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : $value;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}
