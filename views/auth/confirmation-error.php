<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Confirmation link unavailable</title></head>
<body>
<main>
    <h1>Confirmation link unavailable</h1>
    <?php if (isset($error) && is_string($error)): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</main>
</body>
</html>
