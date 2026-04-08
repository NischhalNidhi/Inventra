<?php

declare(strict_types=1);

class ReportController
{
    private Report $reportModel;
    private ReportImportParser $importParser;

    public function __construct(
        Report $reportModel,
        ReportImportParser $importParser
    ) {
        $this->reportModel = $reportModel;
        $this->importParser = $importParser;
    }

    public function validateSale(array $input): array
    {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $unitPrice = filter_var($input['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
        $saleDate = trim($input['sale_date'] ?? '');
        $region = trim($input['region'] ?? '');
        $errors = [];

        if ($productId === false || $productId < 1) {
            $errors[] = 'Product is required.';
        }
        if ($quantity === false || $quantity < 1) {
            $errors[] = 'Quantity must be at least 1.';
        }
        if ($unitPrice === false || $unitPrice <= 0) {
            $errors[] = 'Unit price must be greater than zero.';
        }
        if ($saleDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            $errors[] = 'Sale date must be YYYY-MM-DD.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'product_id' => (int) $productId,
                'quantity' => (int) $quantity,
                'unit_price' => round((float) $unitPrice, 2),
                'sale_date' => $saleDate,
                'region' => $region !== '' ? $region : null,
            ],
        ];
    }

    public function importSales(array $file, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'errors' => ['Please select a valid CSV or XLSX file.']];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            return ['success' => false, 'errors' => ['Only CSV and XLSX files are supported.']];
        }

        $tmpPath = (string) $file['tmp_name'];
        $batchId = $this->reportModel->createImportBatch((string) $file['name'], $extension, 'completed', $userId);

        $imported = 0;
        $skipped = 0;
        try {
            $rows = $this->importParser->parse($tmpPath);
            foreach ($rows as $index => $row) {
                $validated = $this->validateSale([
                    'product_id' => $row['product_id'] ?? null,
                    'quantity' => $row['quantity'] ?? null,
                    'unit_price' => $row['unit_price'] ?? null,
                    'sale_date' => $row['sale_date'] ?? null,
                    'region' => $row['region'] ?? null,
                ]);

                if ($validated['errors']) {
                    $skipped++;
                    $this->reportModel->addImportRowError($batchId, $index + 2, implode(' ', $validated['errors']));
                    continue;
                }

                $this->reportModel->createSale($validated['data'], $userId, 'import');
                $imported++;
            }
            $status = $skipped > 0 ? 'failed' : 'completed';
            $this->reportModel->setImportBatchStats($batchId, $imported, $skipped, $status);
        } catch (Throwable $exception) {
            $this->reportModel->setImportBatchStats($batchId, $imported, $skipped + 1, 'failed');
            return ['success' => false, 'errors' => [$exception->getMessage()]];
        }

        return ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
    }
}
