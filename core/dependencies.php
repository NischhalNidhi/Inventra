<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function buildAppDependencies(): array
{
    $pdo = getDatabaseConnection();

    $userModel = new User($pdo);
    $categoryModel = new Category($pdo);
    $supplierModel = new Supplier($pdo);
    $productModel = new Product($pdo);
    $stockModel = new Stock($pdo);
    $poModel = new PurchaseOrder($pdo);
    $reportModel = new Report($pdo);

    return [
        'pdo' => $pdo,
        'userModel' => $userModel,
        'categoryModel' => $categoryModel,
        'supplierModel' => $supplierModel,
        'productModel' => $productModel,
        'stockModel' => $stockModel,
        'poModel' => $poModel,
        'reportModel' => $reportModel,
        'authController' => new AuthController($userModel),
        'userController' => new UserController($userModel),
        'categoryController' => new CategoryController($categoryModel),
        'supplierController' => new SupplierController($supplierModel),
        'productController' => new ProductController($productModel),
        'stockController' => new StockController($stockModel),
        'poController' => new PurchaseOrderController($poModel),
        'reportController' => new ReportController($reportModel, new ReportImportParser()),
    ];
}
