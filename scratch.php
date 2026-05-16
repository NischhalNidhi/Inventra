<?php
$_SERVER['SCRIPT_NAME'] = '/Inventra/public/index.php';
require 'core/helpers.php';
echo "appRootPath: " . appRootPath('api/reports.php?type=export-daily-csv') . "\n";
echo "basePath: " . basePath('api/reports.php?type=export-daily-csv') . "\n";
