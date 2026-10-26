<?php

declare(strict_types=1);

/** @var Closure(string): string $e */
/** @var string $csrfField */
/** @var string $csrfToken */
/** @var string $token */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Confirm email</title></head>
<body>
<main>
    <h1>Confirm email</h1>
    <p>Submit this form to confirm your email address.</p>
    <form method="post" action="/confirm">
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <input type="hidden" name="<?= $e($csrfField) ?>" value="<?= $e($csrfToken) ?>">
        <button type="submit">Confirm email</button>
    </form>
</main>
</body>
</html>
