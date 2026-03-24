<?php include 'db.php';
if (!isManager()) die("Access denied");

$id = $_GET['id'];

// Keep history (not deleting related tables)
$stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
$stmt->execute([$id]);

header("Location: product.php");
