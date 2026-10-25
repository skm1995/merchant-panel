<?php

// Order report from the old back office, imported as found.
// Usage: /legacy/order-report.php?status=paid&since=2026-01-01

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
);

$status = isset($_GET['status']) ? $_GET['status'] : 'paid';
$since = isset($_GET['since']) ? $_GET['since'] : '2000-01-01';

$sql = "SELECT o.id, o.status, o.total_amount, o.total_currency, o.created_at,
            GROUP_CONCAT(oi.name ORDER BY oi.name SEPARATOR ', ') AS products
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.status = '" . $status . "' AND o.created_at >= '" . $since . "'
        GROUP BY o.id
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT 50";

$result = $pdo->query($sql);
$rows = $result ? $result->fetchAll() : array();

echo "<html><head><meta charset=\"utf-8\"><title>Order report</title></head><body>";
echo "<h1>Orders with status " . $status . " since " . $since . "</h1>";
echo "<table border=\"1\">";
echo "<tr><th>Order</th><th>Products</th><th>Total</th><th>Placed at</th></tr>";
$sum = 0;
foreach ($rows as $row) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['products'] . "</td>";
    echo "<td>" . number_format($row['total_amount'] / 100, 2) . " " . $row['total_currency'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
    $sum = $sum + $row['total_amount'];
}
echo "</table>";
echo "<p>Orders: " . count($rows) . ", total: " . number_format($sum / 100, 2) . "</p>";
echo "</body></html>";
