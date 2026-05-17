<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/AiSalesInsightService.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/ReportImportParser.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/Stock.php';

require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/userController.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../controllers/productController.php';
require_once __DIR__ . '/../controllers/purchaseOrderController.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../controllers/supplierController.php';
require_once __DIR__ . '/../controllers/stockController.php';
