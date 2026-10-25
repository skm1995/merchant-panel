<?php

declare(strict_types=1);

use App\Enum\CharacterEncodingEnum;
use App\Enum\ContentTypeEnum;
use App\Enum\HeaderNameEnum;

header(
    HeaderNameEnum::CONTENT_TYPE->value . ': '
    . ContentTypeEnum::HTML->value,
);
/** @var string $message */
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
        <h1><?= htmlspecialchars($message, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?></h1>
        <?php if (isset($userEmail) && is_string($userEmail)): ?>
            <p>Signed in as <?= htmlspecialchars($userEmail, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?></p>
            <form method="post" action="/logout"><button type="submit">Log out</button></form>
        <?php else: ?>
            <a href="/register">Register</a>
            <a href="/login">Log in</a>
        <?php endif; ?>
    </main>
</body>
</html>
