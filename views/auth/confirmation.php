<?php

declare(strict_types=1);

use App\Shared\Http\Enum\CharacterEncodingEnum;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;

header(HeaderNameEnum::CONTENT_TYPE->value . ': ' . ContentTypeEnum::HTML->value);
/** @var string $token */
/** @var string $csrfToken */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Confirm email</title></head>
<body>
<main>
    <h1>Confirm email</h1>
    <p>Submit this form to confirm your email address.</p>
    <form method="post" action="/confirm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, CharacterEncodingEnum::UTF8->value) ?>">
        <button type="submit">Confirm email</button>
    </form>
</main>
</body>
</html>
