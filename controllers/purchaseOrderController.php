<?php

declare(strict_types=1);

class PurchaseOrderController
{
    public function __construct(private readonly PurchaseOrder $poModel)
    {
    }

    public function validateCreate(array $input): array
    {
        $supplierId = filter_var($input['supplier_id'] ?? null, FILTER_VALIDATE_INT);
        $productIds = $input['line_product_id'] ?? [];
        $quantities = $input['line_quantity'] ?? [];
        $expectedDate = trim($input['expected_date'] ?? '');
        $errors = [];

        if ($supplierId === false || $supplierId < 1) {
            $errors[] = 'Valid supplier is required.';
        }
        if (!is_array($productIds) || !$productIds) {
            $errors[] = 'At least one line item is required.';
        }

        $lineItems = [];
        foreach ($productIds as $index => $productIdValue) {
            if ((string) $productIdValue === '' && (string) ($quantities[$index] ?? '') === '') {
                continue;
            }
            $productId = filter_var($productIdValue, FILTER_VALIDATE_INT);
            $quantityOrdered = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1) {
                $errors[] = 'Every line item must have a valid product.';
                continue;
            }
            if ($quantityOrdered === false || $quantityOrdered < 1) {
                $errors[] = 'Line quantities must be at least 1.';
                continue;
            }

            $lineItems[] = [
                'product_id' => (int) $productId,
                'quantity_ordered' => (int) $quantityOrdered,
            ];
        }

        return [
            'errors' => $errors,
            'data' => [
                'supplier_id' => (int) $supplierId,
                'line_items' => $lineItems,
                'expected_date' => $expectedDate !== '' ? $expectedDate : null,
            ],
        ];
    }

    public function validateTracking(array $input): array
    {
        $carrierName = trim($input['carrier_name'] ?? '');
        $trackingNumber = trim($input['tracking_number'] ?? '');
        $dispatchDate = trim($input['dispatch_date'] ?? '');
        $expectedArrival = trim($input['expected_arrival'] ?? '');
        $shipmentStatus = trim($input['shipment_status'] ?? '');
        $validStatuses = ['order_placed', 'dispatched', 'in_transit', 'delivered'];
        $errors = [];

        if (!in_array($shipmentStatus, $validStatuses, true)) {
            $errors[] = 'Invalid shipment status.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'carrier_name' => $carrierName !== '' ? $carrierName : null,
                'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
                'dispatch_date' => $dispatchDate !== '' ? $dispatchDate : null,
                'expected_arrival' => $expectedArrival !== '' ? $expectedArrival : null,
                'shipment_status' => $shipmentStatus,
            ],
        ];
    }
}
