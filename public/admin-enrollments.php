<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');
$pdo = db();
$errors = [];
$courseFilter = query_int('course') ?: null;
$back = 'admin-enrollments.php' . ($courseFilter ? '?course=' . $courseFilter : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string('action', 20);
    try {
        if ($action === 'enroll') {
            $studentId = post_int('student_id');
            $courseId = post_int('course_id');
            $student = $studentId ? find_user($pdo, $studentId) : null;
            $course = $courseId ? find_course($pdo, $courseId) : null;
            if (!$student || $student['role'] !== 'student' || !$course) {
                $errors[] = 'Pick a student and a course.';
            } else {
                enroll($pdo, $studentId, $courseId);
                audit($pdo, $user['username'], 'enroll', "{$student['username']} in {$course['code']}");
                flash("{$student['full_name']} enrolled in {$course['code']}.");
                redirect($back);
            }
        } elseif ($action === 'grade' && ($e = find_enrollment($pdo, (int) post_int('enrollment_id')))) {
            [$grade, $errors] = parse_grade(post_string('grade', 10));
            if (!$errors) {
                set_grade($pdo, (int) $e['id'], $grade);
                $text = $grade === null ? 'cleared' : number_format($grade, 2);
                audit($pdo, $user['username'], 'grade', "{$e['username']} {$e['code']}: {$text}");
                flash("Grade for {$e['full_name']} in {$e['code']} " . ($grade === null ? 'cleared.' : "set to {$text}."));
                redirect($back);
            }
        } elseif ($action === 'unenroll' && ($e = find_enrollment($pdo, (int) post_int('enrollment_id')))) {
            unenroll($pdo, (int) $e['id']);
            audit($pdo, $user['username'], 'unenroll', "{$e['username']} from {$e['code']}");
            flash("{$e['full_name']} removed from {$e['code']}.");
            redirect($back);
        } else {
            $errors[] = 'Unknown action or enrollment.';
        }
    } catch (PDOException $ex) {
        if (!is_unique_violation($ex)) {
            throw $ex;
        }
        $errors[] = 'That student is already enrolled in that course.';
    }
}

$students = all_students($pdo);
$courses = all_courses($pdo);
$rows = list_enrollments($pdo, $courseFilter);

page_header('Enrollments and grades', $user, 'admin-enrollments.php');
?>
<h1>Enrollments and grades</h1>
<?php show_errors($errors); ?>

<section class="panel">
  <h2>Enroll a student</h2>
  <form class="inline" method="post" data-validate novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="enroll">
    <label>Student
      <select name="student_id" required>
        <option value="">Choose a student</option>
        <?php foreach ($students as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['full_name']) ?> (<?= h($s['username']) ?>)</option><?php endforeach; ?>
      </select>
    </label>
    <label>Course
      <select name="course_id" required>
        <option value="">Choose a course</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseFilter === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['code']) ?>: <?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="btn primary" type="submit">Enroll</button>
  </form>
</section>

<form class="search" method="get">
  <label for="course">Show course</label>
  <select id="course" name="course" data-autosubmit>
    <option value="">All courses</option>
    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseFilter === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['code']) ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="btn" type="submit">Show</button></noscript>
</form>

<div class="table-wrap">
<table>
  <caption><?= count($rows) ?> enrollment<?= count($rows) === 1 ? '' : 's' ?></caption>
  <thead><tr><th>Course</th><th>Student</th><th>Grade (0 to 100)</th><th><span class="sr-only">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $g = grade_of($r); ?>
    <tr>
      <td><?= h($r['code']) ?></td>
      <td><?= h($r['full_name']) ?> <small class="muted"><?= h($r['username']) ?></small></td>
      <td>
        <form class="grade" method="post" data-validate novalidate>
          <?= csrf_field() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="enrollment_id" value="<?= (int) $r['id'] ?>">
          <label class="sr-only" for="g<?= (int) $r['id'] ?>">Grade for <?= h($r['full_name']) ?> in <?= h($r['code']) ?></label>
          <input id="g<?= (int) $r['id'] ?>" name="grade" type="number" min="0" max="100" step="0.01" data-grade value="<?= $g === null ? '' : h((string) $g) ?>" placeholder="none">
          <button class="btn" type="submit">Save</button>
          <span class="letter"><?= h(letter_grade($g)) ?></span>
        </form>
      </td>
      <td class="actions">
        <form method="post" data-confirm="Remove <?= h($r['full_name']) ?> from <?= h($r['code']) ?>? Their grade is deleted too.">
          <?= csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="enrollment_id" value="<?= (int) $r['id'] ?>">
          <button class="link danger" type="submit">Remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="empty">No enrollments yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php
page_footer();
