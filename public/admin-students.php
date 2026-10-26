<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');
$pdo = db();
$errors = [];

// POST handlers. Every success ends in a redirect (Post/Redirect/Get).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string('action', 20);
    $id = post_int('id');
    $username = strtolower(post_string('username', 40));
    $fullName = post_string('full_name', 120);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        if ($action === 'create') {
            $errors = validate_student($username, $fullName, $password, true);
            if (!$errors) {
                create_user($pdo, $username, $fullName, $password);
                audit($pdo, $user['username'], 'student.create', $username);
                flash("Student {$username} created.");
                redirect('admin-students.php');
            }
        } elseif ($action === 'update' && $id !== null && ($target = find_user($pdo, $id)) && $target['role'] === 'student') {
            $errors = validate_student($username, $fullName, $password, false);
            if (!$errors) {
                update_user($pdo, $id, $username, $fullName);
                if ($password !== '') {
                    set_password($pdo, $id, $password);
                }
                audit($pdo, $user['username'], 'student.update', $username . ($password !== '' ? ' (password reset)' : ''));
                flash("Student {$username} saved.");
                redirect('admin-students.php');
            }
        } elseif ($action === 'delete' && $id !== null && ($target = find_user($pdo, $id)) && $target['role'] === 'student') {
            delete_user($pdo, $id);
            audit($pdo, $user['username'], 'student.delete', $target['username']);
            flash("Student {$target['username']} deleted, with their enrollments and grades.");
            redirect('admin-students.php');
        } else {
            $errors[] = 'Unknown action or student.';
        }
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) {
            throw $e;
        }
        $errors[] = 'That username is already taken.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, query_int('page', 1));
$size = config('page_size');
$total = count_students($pdo, $q);
$rows = list_students($pdo, $q, $size, ($page - 1) * $size);
$editing = isset($_GET['edit']) ? find_user($pdo, query_int('edit')) : null;
if ($editing && $editing['role'] !== 'student') {
    $editing = null;
}

page_header('Students', $user, 'admin-students.php');
?>
<h1>Students</h1>
<?php show_errors($errors); ?>

<div class="split">
<section>
  <form class="search" method="get">
    <label class="sr-only" for="q">Search students</label>
    <input id="q" name="q" type="search" value="<?= h($q) ?>" placeholder="Search by name or username">
    <button class="btn" type="submit">Search</button>
  </form>
  <p class="muted"><?= $total ?> student<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching "' . h($q) . '"' : '' ?></p>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Name</th><th>Username</th><th>Courses</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['full_name']) ?></td>
        <td><?= h($r['username']) ?></td>
        <td><?= (int) $r['course_count'] ?></td>
        <td class="actions">
          <a href="?<?= h(http_build_query(['edit' => $r['id'], 'q' => $q, 'page' => $page])) ?>">Edit</a>
          <form method="post" data-confirm="Delete <?= h($r['username']) ?> and all their enrollments and grades?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="link danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="empty">No students found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php pager($page, $total, $size, ['q' => $q]); ?>
</section>

<section class="panel">
  <h2><?= $editing ? 'Edit ' . h($editing['username']) : 'Add a student' ?></h2>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <label>Full name
      <input name="full_name" required minlength="2" maxlength="120" value="<?= h($editing['full_name'] ?? post_string('full_name', 120)) ?>">
    </label>
    <label>Username
      <input name="username" required pattern="[a-z0-9._\-]{3,40}" maxlength="40" value="<?= h($editing['username'] ?? post_string('username', 40)) ?>">
      <small>Lowercase letters, digits, dot, dash or underscore.</small>
    </label>
    <label><?= $editing ? 'New password (leave empty to keep)' : 'Password' ?>
      <input name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72"<?= $editing ? '' : ' required' ?>>
      <small>At least 10 characters.</small>
    </label>
    <button class="btn primary" type="submit"><?= $editing ? 'Save' : 'Add student' ?></button>
    <?php if ($editing): ?><a href="admin-students.php">Cancel</a><?php endif; ?>
  </form>
</section>
</div>
<?php
page_footer();
