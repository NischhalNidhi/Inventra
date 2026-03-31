# Inventra Inventory System (PHP/MySQL)

Inventra is a role-based inventory management system built with PHP, MySQL, HTML/CSS, and JavaScript.

## Stack
- Backend: PHP 8.2+, PDO MySQL
- Frontend: server-rendered PHP views + vanilla JS
- Database: MySQL (XAMPP compatible)

## Setup
1. Copy `.env.example` to `.env` and adjust values if needed.
2. Ensure MySQL is running.
3. Initialize schema:
   ```bash
   php database/init.php
   ```
4. Optional local demo accounts:
   ```bash
   php database/seed_demo_users.php
   ```
5. Open:
   - `http://localhost/inventory-system/public/index.php`

## Default Local Credentials
- Manager: `manager` / `password`
- Supervisor: `supervisor` / `password`
- Salesman: `salesman` / `password`
- Logistic Handler: `logistic` / `password`

## Automated Tests
Run backend + integration + frontend smoke checks:
```bash
php tests/run.php
```

## Notes
- AI features are intentionally disabled in this v1.
- Product image uploads are stored in `uploads/products` and ignored by git.
