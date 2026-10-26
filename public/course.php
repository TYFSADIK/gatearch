<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('student');

// Scoped to the logged-in student: another student's course ID returns nothing.
$course = student_course(db(), $user['id'], query_int('id'));
if ($course === null) {
    http_response_code(404);
}

page_header($course['name'] ?? 'Course not found', $user, 'dashboard.php');
if ($course === null): ?>
  <h1>Course not found</h1>
  <p>You are not enrolled in that course. <a href="dashboard.php">Back to my courses</a></p>
<?php else: $g = grade_of($course); ?>
  <p class="muted"><a href="dashboard.php">My courses</a> / <?= h($course['code']) ?></p>
  <h1><?= h($course['name']) ?></h1>
  <dl class="facts">
    <div><dt>Code</dt><dd><?= h($course['code']) ?></dd></div>
    <div><dt>Credits</dt><dd><?= (int) $course['credits'] ?></dd></div>
    <div><dt>Enrolled</dt><dd><?= h(substr($course['enrolled_at'], 0, 10)) ?></dd></div>
    <div><dt>Grade</dt><dd><?= $g === null ? 'Pending' : h(number_format($g, 2)) . ' (' . h(letter_grade($g)) . ')' ?></dd></div>
    <?php if ($course['updated_at']): ?><div><dt>Graded on</dt><dd><?= h(substr($course['updated_at'], 0, 10)) ?></dd></div><?php endif; ?>
  </dl>
  <h2>About this course</h2>
  <p><?= nl2br(h($course['description'] ?: 'No description yet.')) ?></p>
<?php endif;
page_footer();
