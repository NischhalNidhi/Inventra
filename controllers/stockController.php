<?php

declare(strict_types=1);

class StockController
{
    private Stock $stockModel;

    public function __construct(Stock $stockModel)
    {
        $this->stockModel = $stockModel;
    }

    public function validateStockIn(array $input): array
    {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim($input['reason'] ?? '');
        $date = trim($input['date'] ?? '');

        $errors = [];

        if ($productId === false || $productId < 1) {
            $errors[] = 'Please select a valid product.';
        }

        if ($quantity === false || $quantity <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }

        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'Invalid date format.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'product_id' => $productId,
                'quantity' => $quantity,
                'reason' => $reason !== '' ? $reason : null,
                'date' => $date !== '' ? $date : date('Y-m-d'),
            ],
        ];
    }

    public function handleStockIn(array $input, int $userId): array
    {
        $validated = $this->validateStockIn($input);

        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        return $this->stockModel->processStockIn(
            $validated['data']['product_id'],
            $validated['data']['quantity'],
            $validated['data']['reason'] ?? '',
            $userId
        );
    }

    public function validateStockOut(array $input): array
    {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim($input['reason'] ?? '');
        $date = trim($input['date'] ?? '');

        $errors = [];

        if ($productId === false || $productId < 1) {
            $errors[] = 'Please select a valid product.';
        }

        if ($quantity === false || $quantity <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }

        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'Invalid date format.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'product_id' => $productId,
                'quantity' => $quantity,
                'reason' => $reason !== '' ? $reason : null,
                'date' => $date !== '' ? $date : date('Y-m-d'),
            ],
        ];
    }

    public function handleStockOut(array $input, int $userId): array
    {
        $validated = $this->validateStockOut($input);

        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        return $this->stockModel->processStockOut(
            $validated['data']['product_id'],
            $validated['data']['quantity'],
            $validated['data']['reason'] ?? '',
            $userId
        );
    }

    public function handleAdjustment(array $input, int $userId): array
    {
        $movementType = $input['movement_type'] ?? '';

        if ($movementType === 'in') {
            return $this->handleStockIn($input, $userId);
        }

        if ($movementType === 'out') {
            return $this->handleStockOut($input, $userId);
        }

        return ['success' => false, 'errors' => ['Invalid movement type.']];
    }
}