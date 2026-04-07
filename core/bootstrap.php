<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../dev2/models/User.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../dev3/models/Supplier.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../dev4/models/Stock.php';
require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../dev2/models/Report.php';
require_once __DIR__ . '/../dev2/models/ReportImportParser.php';

require_once __DIR__ . '/../dev2/controllers/authController.php';
require_once __DIR__ . '/../dev2/controllers/userController.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../dev3/controllers/supplierController.php';
require_once __DIR__ . '/../controllers/productController.php';
require_once __DIR__ . '/../dev4/controllers/stockController.php';
require_once __DIR__ . '/../controllers/purchaseOrderController.php';
require_once __DIR__ . '/../dev2/controllers/reportController.php';
