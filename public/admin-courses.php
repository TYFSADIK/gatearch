<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string('action', 20);
    $id = post_int('id');
    $code = strtoupper(post_string('code', 20));
    $name = post_string('name', 120);
    $credits = post_int('credits');
    $description = post_string('description', 2000);

    try {
        if ($action === 'create') {
            $errors = validate_course($code, $name, $credits);
            if (!$errors) {
                create_course($pdo, $code, $name, $credits, $description);
                audit($pdo, $user['username'], 'course.create', "{$code} {$name}");
                flash("Course {$code} created.");
                redirect('admin-courses.php');
            }
        } elseif ($action === 'update' && $id !== null && find_course($pdo, $id)) {
            $errors = validate_course($code, $name, $credits);
            if (!$errors) {
                update_course($pdo, $id, $code, $name, $credits, $description);
                audit($pdo, $user['username'], 'course.update', "{$code} {$name}");
                flash("Course {$code} saved.");
                redirect('admin-courses.php');
            }
        } elseif ($action === 'delete' && $id !== null && ($course = find_course($pdo, $id))) {
            delete_course($pdo, $id);
            audit($pdo, $user['username'], 'course.delete', "{$course['code']} {$course['name']}");
            flash("Course {$course['code']} deleted, with its enrollments and grades.");
            redirect('admin-courses.php');
        } else {
            $errors[] = 'Unknown action or course.';
        }
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) {
            throw $e;
        }
        $errors[] = 'That course code is already used.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, query_int('page', 1));
$size = config('page_size');
$total = count_courses($pdo, $q);
$rows = list_courses($pdo, $q, $size, ($page - 1) * $size);
$editing = isset($_GET['edit']) ? find_course($pdo, query_int('edit')) : null;

page_header('Courses', $user, 'admin-courses.php');
?>
<h1>Courses</h1>
<?php show_errors($errors); ?>

<div class="split">
<section>
  <form class="search" method="get">
    <label class="sr-only" for="q">Search courses</label>
    <input id="q" name="q" type="search" value="<?= h($q) ?>" placeholder="Search by code or name">
    <button class="btn" type="submit">Search</button>
  </form>
  <p class="muted"><?= $total ?> course<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching "' . h($q) . '"' : '' ?></p>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Credits</th><th>Students</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['code']) ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= (int) $r['credits'] ?></td>
        <td><a href="admin-enrollments.php?course=<?= (int) $r['id'] ?>"><?= (int) $r['student_count'] ?></a></td>
        <td class="actions">
          <a href="?<?= h(http_build_query(['edit' => $r['id'], 'q' => $q, 'page' => $page])) ?>">Edit</a>
          <form method="post" data-confirm="Delete <?= h($r['code']) ?> and every enrollment and grade in it?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="link danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">No courses found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php pager($page, $total, $size, ['q' => $q]); ?>
</section>

<section class="panel">
  <h2><?= $editing ? 'Edit ' . h($editing['code']) : 'Add a course' ?></h2>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <label>Code
      <input name="code" required maxlength="20" placeholder="CS101" value="<?= h($editing['code'] ?? post_string('code', 20)) ?>">
    </label>
    <label>Name
      <input name="name" required minlength="3" maxlength="120" value="<?= h($editing['name'] ?? post_string('name', 120)) ?>">
    </label>
    <label>Credits
      <input name="credits" type="number" required min="1" max="12" step="1" value="<?= h((string) ($editing['credits'] ?? post_string('credits', 2))) ?>">
    </label>
    <label>Description
      <textarea name="description" rows="5" maxlength="2000"><?= h($editing['description'] ?? post_string('description', 2000)) ?></textarea>
    </label>
    <button class="btn primary" type="submit"><?= $editing ? 'Save' : 'Add course' ?></button>
    <?php if ($editing): ?><a href="admin-courses.php">Cancel</a><?php endif; ?>
  </form>
</section>
</div>
<?php
page_footer();
