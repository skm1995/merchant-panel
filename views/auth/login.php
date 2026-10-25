<?php

declare(strict_types=1);

/** @var Closure(string): string $e */
/** @var string $csrfField */
/** @var string $csrfToken */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in</title>
</head>
<body>
<main>
    <h1>Log in</h1>
    <?php if (isset($error) && is_string($error)): ?>
        <p role="alert"><?= $e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/login">
        <input type="hidden" name="<?= $e($csrfField) ?>" value="<?= $e($csrfToken) ?>">
        <label>Email <input type="email" name="email"<?php if (isset($email) && is_string($email)): ?> value="<?= $e($email) ?>"<?php endif; ?> required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
    </form>
    <a href="/register">Create account</a>
</main>
</body>
</html>
