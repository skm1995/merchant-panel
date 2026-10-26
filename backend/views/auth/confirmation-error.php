<?php

declare(strict_types=1);

/** @var Closure(string): string $e */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Confirmation link unavailable</title></head>
<body>
<main>
    <h1>Confirmation link unavailable</h1>
    <?php if (isset($error) && is_string($error)): ?>
        <p><?= $e($error) ?></p>
    <?php endif; ?>
</main>
</body>
</html>
