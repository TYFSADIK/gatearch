<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$s = stats(db());
$recent = list_audit(db(), 8, 0);

page_header('Overview', $user, 'admin.php');
?>
<h1>Overview</h1>
<div class="stats">
  <div><span><?= $s['students'] ?></span><a href="admin-students.php">Students</a></div>
  <div><span><?= $s['courses'] ?></span><a href="admin-courses.php">Courses</a></div>
  <div><span><?= $s['enrollments'] ?></span><a href="admin-enrollments.php">Enrollments</a></div>
  <div><span><?= $s['ungraded'] ?></span>Waiting for a grade</div>
</div>

<h2>Recent changes</h2>
<?php if (!$recent): ?>
  <p class="empty">Nothing yet. Changes made in the admin pages appear here.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $r): ?>
    <tr><td><?= h($r['created_at']) ?></td><td><?= h($r['actor']) ?></td><td><?= h($r['action']) ?></td><td><?= h($r['detail']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p><a href="admin-audit.php">Full audit log</a></p>
<?php endif;
page_footer();
