<?php

declare(strict_types=1);

class CategoryController
{
    public function __construct(private readonly Category $categoryModel)
    {
    }

    public function validate(array $input): array
    {
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $errors = [];

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Category name must be at least 2 characters.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
            ],
        ];
    }
}
