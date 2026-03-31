<?php

declare(strict_types=1);

class SupplierController
{
    public function __construct(private readonly Supplier $supplierModel)
    {
    }

    public function validate(array $input): array
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
            ],
        ];
    }
}
