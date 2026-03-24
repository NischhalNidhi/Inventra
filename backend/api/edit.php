<?php include 'db.php';
if (!isManager()) die("Access denied");

$id = $_GET['id'];
$product = $pdo->query("SELECT * FROM products WHERE id=$id")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];

    if (!$name) {
        echo "Name required";
    } else {
        $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, price=?, quantity=?, threshold=?, description=? WHERE id=?");
        $stmt->execute([
            $name,
            $_POST['category'],
            $_POST['price'],
            $_POST['quantity'],
            $_POST['threshold'],
            $_POST['description'],
            $id
        ]);

        header("Location: index.php");
    }
}
?>

<h2>Edit Product</h2>

<form method="POST">
    Name: <input name="name" value="<?= $product['name'] ?>"><br>
    SKU: <input value="<?= $product['sku'] ?>" disabled><br>
    Category: <input name="category" value="<?= $product['category'] ?>"><br>
    Price: <input name="price" value="<?= $product['price'] ?>"><br>
    Quantity: <input name="quantity" value="<?= $product['quantity'] ?>"><br>
    Threshold: <input name="threshold" value="<?= $product['threshold'] ?>"><br>
    Description: <textarea name="description"><?= $product['description'] ?></textarea><br>
    <button>Update</button>
</form>
