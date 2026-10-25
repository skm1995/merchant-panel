<?php

declare(strict_types=1);

/** @var Closure(string): string $e */
/** @var string $csrfField */
/** @var string $csrfToken */
/** @var string|null $userEmail */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP MVC Playground</title>
</head>
<body>
<main>
    <h1>PHP MVC Playground</h1>
    <?php if ($userEmail !== null): ?>
        <p>Signed in as <?= $e($userEmail) ?></p>
        <form method="post" action="/logout">
            <input type="hidden" name="<?= $e($csrfField) ?>" value="<?= $e($csrfToken) ?>">
            <button type="submit">Log out</button>
        </form>
    <?php else: ?>
        <a href="/register">Register</a>
        <a href="/login">Log in</a>
    <?php endif; ?>
</main>
</body>
</html>
