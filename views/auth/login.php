<?php

declare(strict_types=1);

use App\Enum\CharacterEncodingEnum;
use App\Enum\ContentTypeEnum;
use App\Enum\HeaderNameEnum;

header(HeaderNameEnum::CONTENT_TYPE->value . ': ' . ContentTypeEnum::HTML->value);
$emailValue = isset($email) && is_string($email)
    ? htmlspecialchars($email, ENT_QUOTES, CharacterEncodingEnum::UTF8->value)
    : null;
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
        <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?></p>
    <?php endif; ?>
    <form method="post" action="/login">
        <label>Email <input type="email" name="email"<?php if ($emailValue !== null): ?> value="<?= $emailValue ?>"<?php endif; ?> required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
    </form>
    <a href="/register">Create account</a>
</main>
</body>
</html>
