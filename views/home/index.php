<?php

declare(strict_types=1);

use App\Shared\Http\Enum\CharacterEncodingEnum;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;

header(
    HeaderNameEnum::CONTENT_TYPE->value . ': '
    . ContentTypeEnum::HTML->value,
);
/** @var string $csrfToken */
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
        <?php if (isset($userEmail) && is_string($userEmail)): ?>
            <p>Signed in as <?= htmlspecialchars($userEmail, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?></p>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?>">
                <button type="submit">Log out</button>
            </form>
        <?php else: ?>
            <a href="/register">Register</a>
            <a href="/login">Log in</a>
        <?php endif; ?>
    </main>
</body>
</html>
