<?php

declare(strict_types=1);

class SupplierController
{
    private Supplier $supplierModel;

    public function __construct(Supplier $supplierModel)
    {
        $this->supplierModel = $supplierModel;
    }

    public function validate(array $input, array $files = [], ?string $fallbackImage = null): array
    {
        $name = trim($input['name'] ?? '');
        $contactPerson = trim($input['contact_person'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $errors = [];

        if ($name === '') {
            $errors[] = 'Supplier name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid supplier email is required.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'contact_person' => $contactPerson !== '' ? $contactPerson : null,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'image_name' => $this->handleImageUpload($files, $fallbackImage),
            ],
        ];
    }

    private function handleImageUpload(array $files, ?string $fallbackImage): ?string
    {
        $file = $files['supplier_image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $fallbackImage;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Supplier image upload failed.');
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('Supplier image must be 2MB or smaller.');
        }

        $mime = mime_content_type($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, and WEBP supplier images are allowed.');
        }

        $uploadDir = dirname(__DIR__) . '/public/uploads/suppliers';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create supplier upload directory.');
        }

        $fileName = sprintf('supplier_%s.%s', bin2hex(random_bytes(10)), $allowed[$mime]);
        $targetPath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to save supplier image.');
        }

        return $fileName;
    }
}
