<?php

declare(strict_types=1);

class CategoryController
{
    private Category $categoryModel;

    public function __construct(Category $categoryModel)
    {
        $this->categoryModel = $categoryModel;
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
