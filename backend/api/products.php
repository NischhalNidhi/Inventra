<?php include 'db.php'; ?>

<?php
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? LIMIT $limit OFFSET $offset");
$stmt->execute(["%$search%"]);
$products = $stmt->fetchAll();

$total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
?>

<h2>Product List</h2>

<form method="GET">
    <input type="text" name="search" placeholder="Search..." />
    <button type="submit">Search</button>
</form>

<?php if (!$products): ?>
    <p>No products found.</p>
<?php else: ?>
<table border="1">
    <tr>
        <th>Name</th>
        <th>SKU</th>
        <th>Category</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

<?php foreach ($products as $p): ?>
<tr>
    <td><?= $p['name'] ?></td>
    <td><?= $p['sku'] ?></td>
    <td><?= $p['category'] ?></td>
    <td>$<?= $p['price'] ?></td>
    <td><?= $p['quantity'] ?></td>
    <td>
        <?php
        if ($p['quantity'] == 0) echo "Out of Stock";
        elseif ($p['quantity'] <= $p['threshold']) echo "Low";
        else echo "In Stock";
        ?>
    </td>
    <td>
        <?php if (isManager()): ?>
            <a href="edit.php?id=<?= $p['id'] ?>">Edit</a>
            <a href="delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Pagination -->
<?php for ($i = 1; $i <= ceil($total/$limit); $i++): ?>
    <a href="?page=<?= $i ?>"><?= $i ?></a>
<?php endfor; ?>

<?php if (isManager()): ?>
    <a href="add.php">Add Product</a>
<?php endif; ?>
