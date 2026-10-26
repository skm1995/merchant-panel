<?php
// Product stock page, imported as found in the old back office: merchants open
// /legacy/product-stock.php?id=<product> to change a product's price and stock.
// It is excluded from PHPStan and PHP CS Fixer until it is replaced.

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
);

$id = isset($_GET['id']) ? $_GET['id'] : '0';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdo->exec("UPDATE products SET price_amount = " . $_POST['price'] . ", stock = " . $_POST['stock'] . " WHERE id = " . $id);
    $message = 'Saved.';
}

$product = $pdo->query("SELECT sku, name, price_amount, price_currency, stock, reserved FROM products WHERE id = " . $id)->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('HTTP/1.1 404 Not Found');
    echo 'Product not found';
    exit;
}
?>
<html>
<head><title>Product stock</title></head>
<body>
<h1>Product <?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</h1>
<?php if ($message) { echo '<p class="message">' . $message . '</p>'; } ?>
<form method="post">
    Price (<?php echo $product['price_currency']; ?>, minor units): <input name="price" value="<?php echo $product['price_amount']; ?>">
    Stock: <input name="stock" value="<?php echo $product['stock']; ?>">
    Reserved: <?php echo $product['reserved']; ?>
    <button type="submit">Save</button>
</form>
</body>
</html>
