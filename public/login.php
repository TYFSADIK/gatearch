<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (current_user() !== null) {
    redirect('index.php');
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(post_string('username', 80));
    $password = $_POST['password'] ?? '';
    [$status, $user] = attempt_login(db(), $username, is_string($password) ? $password : '', client_ip());
    if ($status === LOGIN_OK) {
        sign_in($user);
        redirect($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php');
    }
    $error = $status === LOGIN_LOCKED
        ? 'Too many failed attempts. Wait 15 minutes and try again.'
        : 'Wrong username or password.';
}

page_header('Log in', null);
?>
<section class="login-card">
  <h1>GateArch</h1>
  <p class="muted">Student portal and course administration</p>
  <?php if ($error): ?><p class="flash error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label>Username
      <input name="username" autocomplete="username" required maxlength="80" value="<?= h($username) ?>" autofocus>
    </label>
    <label>Password
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn primary">Log in</button>
  </form>
</section>
<?php
page_footer();
