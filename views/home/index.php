<?php

declare(strict_types=1);

header(
    \App\Enum\HeaderNameEnum::CONTENT_TYPE->value . ': '
    . \App\Enum\ContentTypeEnum::HTML->value,
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
        <h1><?= htmlspecialchars($message, ENT_QUOTES, \App\Enum\CharacterEncodingEnum::UTF8->value) ?></h1>
    </main>
</body>
</html>
