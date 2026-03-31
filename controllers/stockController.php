<?php

declare(strict_types=1);

class StockController
{
    public function __construct(private readonly Stock $stockModel)
    {
    }

    public function validateMovement(array $input): array
    {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $movementType = $input['movement_type'] ?? '';
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim($input['reason'] ?? '');

        $errors = [];

        if ($productId === false || $productId < 1) {
            $errors[] = 'A valid product must be selected.';
        }

        if (!in_array($movementType, ['in', 'out'], true)) {
            $errors[] = 'Invalid stock movement type.';
        }

        if ($quantity === false || $quantity < 1) {
            $errors[] = 'Quantity must be at least 1.';
        }

        if ($reason === '') {
            $errors[] = 'A short note is required for stock history.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'product_id' => (int) $productId,
                'movement_type' => $movementType,
                'quantity' => (int) $quantity,
                'reason' => $reason,
            ],
        ];
    }

    public function handleAdjustment(array $input, int $userId): array
    {
        $validated = $this->validateMovement($input);

        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        $result = $this->stockModel->adjustStock(
            $validated['data']['product_id'],
            $validated['data']['movement_type'],
            $validated['data']['quantity'],
            $validated['data']['reason'],
            $userId
        );

        return [
            'success' => true,
            'message' => sprintf(
                '%s moved stock %s. Quantity changed from %d to %d.',
                $result['product_name'],
                $validated['data']['movement_type'] === 'in' ? 'in' : 'out',
                $result['previous_quantity'],
                $result['new_quantity']
            ),
        ];
    }
}
