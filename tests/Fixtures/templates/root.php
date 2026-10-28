<?php

/**
 * @var string $head
 * @var string $body
 * @var array<string, mixed> $page
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(is_string($title ?? null) ? $title : 'App') ?></title>
    <?= $head ?>
</head>
<body>
    <?= $body ?>
</body>
</html>
