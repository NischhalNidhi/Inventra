<?php

declare(strict_types=1);

class ProductController
{
    private Product $productModel;

    public function __construct(Product $productModel)
    {
        $this->productModel = $productModel;
    }

    public function validate(array $input, ?int $productId = null): array
    {
        $name = trim($input['name'] ?? '');
        $sku = strtoupper(trim($input['sku'] ?? ''));
        $description = trim($input['description'] ?? '');
        $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
        $supplierId = filter_var($input['supplier_id'] ?? null, FILTER_VALIDATE_INT);
        $stockQuantity = filter_var($input['stock_quantity'] ?? null, FILTER_VALIDATE_INT);
        $minThreshold = filter_var($input['min_threshold'] ?? null, FILTER_VALIDATE_INT);
        $unitPrice = filter_var($input['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
        $imageName = trim($input['image_name'] ?? '');

        $errors = [];

        if ($name === '' || mb_strlen($name) < 3) {
            $errors[] = 'Product name must be at least 3 characters.';
        }

        if ($sku === '' || !preg_match('/^[A-Z0-9-]{4,30}$/', $sku)) {
            $errors[] = 'SKU must be 4-30 characters using letters, numbers, or dashes.';
        }

        if ($categoryId === false || $categoryId < 1) {
            $errors[] = 'Please select a valid category.';
        }

        if ($stockQuantity === false || $stockQuantity < 0) {
            $errors[] = 'Initial quantity must be zero or greater.';
        }

        if ($minThreshold === false || $minThreshold < 0) {
            $errors[] = 'Minimum stock must be zero or greater.';
        }

        if ($unitPrice === false || $unitPrice < 0) {
            $errors[] = 'Unit price must be zero or greater.';
        }

        if ($supplierId !== false && $supplierId !== null && $supplierId < 1) {
            $errors[] = 'Supplier must be valid when selected.';
        }

        if ($this->productModel->skuExists($sku, $productId)) {
            $errors[] = 'That SKU already exists.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'sku' => $sku,
                'description' => $description !== '' ? $description : null,
                'category_id' => (int) $categoryId,
                'supplier_id' => $supplierId ? (int) $supplierId : null,
                'stock_quantity' => (int) $stockQuantity,
                'min_threshold' => (int) $minThreshold,
                'unit_price' => (float) $unitPrice,
                'image_name' => $imageName,
            ],
        ];
    }

    public function handleCreate(array $input, array $files, int $userId): array
    {
        $validated = $this->validate($input);

        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        $imageName = $this->handleImageUpload($files, $validated['data']['image_name']);
        $validated['data']['image_name'] = $imageName;

        $this->productModel->create($validated['data'], $userId);

        return ['success' => true];
    }

    public function handleUpdate(int $id, array $input, array $files, int $userId): array
    {
        $validated = $this->validate($input, $id);

        if ($validated['errors']) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        $imageName = $this->handleImageUpload($files, $validated['data']['image_name']);
        if ($imageName !== null) {
            $validated['data']['image_name'] = $imageName;
        } else {
            $existing = $this->productModel->findById($id);
            $validated['data']['image_name'] = $existing['image_name'] ?? '';
        }

        $this->productModel->update($id, $validated['data'], $userId);

        return ['success' => true];
    }

    public function handleDelete(int $id): void
    {
        $this->productModel->delete($id);
    }

    public function handleArchive(int $id): void
    {
        $this->productModel->archive($id);
    }

    private function handleImageUpload(array $files, string $fallback): ?string
    {
        $file = $files['product_image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $fallback !== '' ? $fallback : null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('Image must be 2MB or smaller.');
        }

        $mime = mime_content_type($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
        }

        $uploadDir = dirname(__DIR__) . '/public/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $fileName = sprintf('%s.%s', bin2hex(random_bytes(12)), $allowed[$mime]);
        $targetPath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        return $fileName;
    }
}
