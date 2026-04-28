<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function buildAppDependencies(): array
{
    $pdo = getDatabaseConnection();

    $userModel = new User($pdo);
    $categoryModel = new Category($pdo);
    $productModel = new Product($pdo);
    $poModel = new PurchaseOrder($pdo);
    $reportModel = new Report($pdo);
    $supplierModel = new Supplier($pdo);
    $mailer = new Mailer();
    $aiSalesInsightService = new AiSalesInsightService();

    return [
        'pdo' => $pdo,
        'mailer' => $mailer,
        'userModel' => $userModel,
        'categoryModel' => $categoryModel,
        'productModel' => $productModel,
        'poModel' => $poModel,
        'reportModel' => $reportModel,
        'supplierModel' => $supplierModel,
        'aiSalesInsightService' => $aiSalesInsightService,
        'authController' => new AuthController($userModel, $mailer, $pdo),
        'userController' => new UserController($userModel),
        'categoryController' => new CategoryController($categoryModel),
        'productController' => new ProductController($productModel),
        'poController' => new PurchaseOrderController($poModel),
        'reportController' => new ReportController($reportModel, new ReportImportParser()),
        'supplierController' => new SupplierController($supplierModel),
    ];
}
