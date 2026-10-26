<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$page = max(1, query_int('page', 1));
$size = 50;
$total = count_audit(db());
$rows = list_audit(db(), $size, ($page - 1) * $size);

page_header('Audit log', $user, 'admin-audit.php');
?>
<h1>Audit log</h1>
<p class="muted">Every change made in the admin pages: who, what and when. <?= $total ?> entries.</p>
<div class="table-wrap">
<table>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= h($r['created_at']) ?></td><td><?= h($r['actor']) ?></td><td><?= h($r['action']) ?></td><td><?= h($r['detail']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="empty">No entries yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php
pager($page, $total, $size);
page_footer();
