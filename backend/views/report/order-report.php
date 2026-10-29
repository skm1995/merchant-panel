<?php

declare(strict_types=1);

/** @var Closure(string): string $e */
/** @var array{heading: string, rows: list<array{string, ?string, string, string}>, summary: string} $page */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Order report</title></head>
<body>
<main>
    <h1><?= $e($page['heading']) ?></h1>
    <table>
        <tr><th>Order</th><th>Products</th><th>Total</th><th>Placed at</th></tr>
        <?php foreach ($page['rows'] as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?>
                    <td><?php if ($cell !== null): ?><?= $e($cell) ?><?php endif; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>
    <p><?= $e($page['summary']) ?></p>
</main>
</body>
</html>
