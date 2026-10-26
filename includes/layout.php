<?php
declare(strict_types=1);

function page_header(string $title, ?array $user, string $active = ''): void
{
    $nav = [];
    if ($user !== null && $user['role'] === 'admin') {
        $nav = ['admin.php' => 'Overview', 'admin-students.php' => 'Students', 'admin-courses.php' => 'Courses',
                'admin-enrollments.php' => 'Enrollments and grades', 'admin-audit.php' => 'Audit log'];
    } elseif ($user !== null) {
        $nav = ['dashboard.php' => 'My courses'];
    }
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | GateArch</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body class="<?= $user ? 'app' : 'guest' ?>">
<?php if ($user !== null): ?>
  <aside class="sidebar">
    <a class="brand" href="<?= $user['role'] === 'admin' ? 'admin.php' : 'dashboard.php' ?>">GateArch</a>
    <nav aria-label="Main">
      <?php foreach ($nav as $href => $label): ?>
        <a href="<?= h($href) ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="who">
      <span><?= h($user['name']) ?></span>
      <small><?= h($user['role']) ?></small>
      <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="link">Log out</button></form>
    </div>
  </aside>
<?php endif; ?>
  <main class="content">
    <?php foreach (take_flashes() as [$type, $message]): ?>
      <p class="flash <?= h($type) ?>" role="status"><?= h($message) ?></p>
    <?php endforeach; ?>
<?php
}

function page_footer(): void
{
    ?>
  </main>
</body>
</html>
<?php
}

function pager(int $page, int $total, int $size, array $query = []): void
{
    $pages = max(1, (int) ceil($total / $size));
    if ($pages === 1) {
        return;
    }
    echo '<nav class="pager" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        $href = '?' . http_build_query([...$query, 'page' => $i]);
        echo $i === $page
            ? '<span aria-current="page">' . $i . '</span>'
            : '<a href="' . h($href) . '">' . $i . '</a>';
    }
    echo '</nav>';
}

function show_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="flash error" role="alert"><ul>';
    foreach ($errors as $e) {
        echo '<li>' . h($e) . '</li>';
    }
    echo '</ul></div>';
}
