<?php include 'db.php'; 
if (!isManager()) die("Access denied");

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $sku = $_POST['sku'];

    if (!$name || !$sku) {
        $error = "Name and SKU required";
    } else {
        // Check duplicate SKU
        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku=?");
        $check->execute([$sku]);

        if ($check->fetchColumn() > 0) {
            $error = "SKU already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, sku, category, price, quantity, threshold, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $sku,
                $_POST['category'],
                $_POST['price'],
                $_POST['quantity'],
                $_POST['threshold'],
                $_POST['description']
            ]);

            header("Location: index.php?success=1");
            exit;
        }
    }
}
?>

<h2>Add Product</h2>
<p style="color:red;"><?= $error ?></p>

<form method="POST">
    Name: <input name="name"><br>
    SKU: <input name="sku"><br>
    Category: <input name="category"><br>
    Price: <input name="price"><br>
    Quantity: <input name="quantity"><br>
    Threshold: <input name="threshold"><br>
    Description: <textarea name="description"></textarea><br>
    <button type="submit">Save</button>
</form>
