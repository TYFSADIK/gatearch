<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('student');

$courses = student_courses(db(), $user['id']);
$average = weighted_average($courses);
$credits = array_sum(array_map(fn ($c) => (int) $c['credits'], $courses));
$graded = count(array_filter($courses, fn ($c) => $c['grade'] !== null));

page_header('My courses', $user, 'dashboard.php');
?>
<h1>Welcome, <?= h($user['name']) ?></h1>
<div class="stats">
  <div><span><?= count($courses) ?></span>Courses</div>
  <div><span><?= $credits ?></span>Credits</div>
  <div><span><?= $graded ?>/<?= count($courses) ?></span>Graded</div>
  <div><span><?= $average === null ? 'n/a' : h(number_format($average, 2)) ?></span>Credit-weighted average</div>
</div>

<?php if (!$courses): ?>
  <p class="empty">You are not enrolled in any course yet. Your administrator adds enrollments.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <caption>Enrolled courses</caption>
  <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th>Grade</th><th>Letter</th></tr></thead>
  <tbody>
  <?php foreach ($courses as $c): $g = grade_of($c); ?>
    <tr>
      <td><?= h($c['code']) ?></td>
      <td><a href="course.php?id=<?= (int) $c['id'] ?>"><?= h($c['name']) ?></a></td>
      <td><?= (int) $c['credits'] ?></td>
      <td><?= $g === null ? '<span class="muted">Pending</span>' : h(number_format($g, 2)) ?></td>
      <td><?= h(letter_grade($g)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php
page_footer();
