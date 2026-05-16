# Inventra — Inventory Management System

A web-based inventory management system built with **PHP** and **MySQL**, running on **XAMPP** (Apache).

---

## How to Run Locally (XAMPP)

1. Copy this project into `C:\xampp\htdocs\Inventra`
2. Copy `.env.example` → `.env` (no changes needed for XAMPP)
3. Start **Apache** and **MySQL** in the XAMPP Control Panel
4. Open a browser and go to: `http://localhost/Inventra/public/`
5. The database is **created automatically** on first visit

**Default login:**
- Username: `manager`
- Password: `password`

---

## Project Structure

```
Inventra/
│
├── public/              ← The only folder the browser can access
│   ├── index.php        ← Single entry point for the whole app (front controller)
│   ├── css/style.css    ← All the styling
│   └── js/app.js        ← All the frontend JavaScript
│
├── views/               ← HTML pages (one folder per feature)
│   ├── auth/            ← Login / forgot password page
│   ├── dashboard/       ← Dashboard page
│   ├── products/        ← Inventory list + add/edit form
│   ├── categories/      ← Product categories
│   ├── suppliers/       ← Supplier management
│   ├── purchase-orders/ ← PO Tracker
│   ├── logistics/       ← Delivery log & reorder
│   ├── reports/         ← Sales & inventory reports
│   ├── users/           ← Staff account management
│   └── partials/        ← Reusable UI snippets (e.g. pagination)
│
├── models/              ← Database logic (one file per table)
│   ├── User.php
│   ├── Product.php
│   ├── Category.php
│   ├── Supplier.php
│   ├── PurchaseOrder.php
│   └── Report.php
│
├── controllers/         ← Business logic & form validation
│   ├── authController.php
│   ├── productController.php
│   ├── categoryController.php
│   ├── supplierController.php
│   ├── purchaseOrderController.php
│   ├── reportController.php
│   └── userController.php
│
├── api/                 ← AJAX endpoints (JSON responses)
│   ├── auth.php
│   ├── products.php
│   ├── categories.php
│   ├── suppliers.php
│   ├── purchase_orders.php
│   ├── reports.php
│   ├── stock.php
│   ├── users.php
│   ├── logistics.php
│   ├── dashboard.php
│   └── mock_ai.php      ← Local AI insight simulation
│
├── core/                ← Shared utilities loaded by everything
│   ├── helpers.php      ← Global functions (e(), env(), redirectTo(), etc.)
│   ├── bootstrap.php    ← Loads all files in the right order
│   ├── dependencies.php ← Creates all models/controllers and returns them
│   ├── Mailer.php       ← Sends emails (or logs them locally)
│   ├── AiSalesInsightService.php ← Calls external AI API for sales insight
│   └── layout/
│       ├── header.php   ← Top navigation + HTML <head>
│       └── navbar.php   ← Left sidebar navigation
│
├── config/
│   └── db.php           ← Creates the PDO database connection
│
├── database/
│   ├── schema.sql        ← All table definitions (CREATE TABLE statements)
│   ├── bootstrap.php     ← Functions to create/rebuild the database
│   ├── init.php          ← Run once to create tables
│   └── rebuild.php       ← Drop and recreate all tables (resets data)
│
├── uploads/              ← Stores user-uploaded product images
├── logo/                 ← Inventra brand logo images
├── .env                  ← Your local settings (DB password, etc.) — not in Git
├── .env.example          ← Template showing what .env should look like
├── .gitignore
└── Dockerfile            ← For deploying to cloud (Docker/Railway)
```

---

## Architecture Overview

This app follows the **MVC pattern** (Model-View-Controller):

| Layer | Folder | Responsibility |
|---|---|---|
| **Model** | `models/` | All SQL queries. Talks directly to the database. |
| **View** | `views/` | HTML templates shown to the user. |
| **Controller** | `controllers/` | Validates form input, applies business rules, calls the model. |

### Request Flow

```
Browser → public/index.php → loads dependencies → calls controller → loads view
                  ↑
         (all API calls go to api/*.php instead)
```

1. Every page request hits `public/index.php`
2. `index.php` reads the `?page=` URL parameter to decide what to show
3. It loads `core/dependencies.php` which sets up the database and all objects
4. It then runs the correct controller logic and renders the matching view

---

## Key Concepts (for Viva)

### Why `public/` is the only web-accessible folder?
- It prevents users from accessing PHP source code, config files, or the `.env` file directly via browser.
- Apache only serves files from `public/`. Everything else is protected.

### What is `core/helpers.php`?
- Contains small utility functions used everywhere in the app.
- `e()` — escapes output to prevent XSS attacks.
- `env()` — reads settings from the `.env` file.
- `csrfToken()` / `verifyCsrfToken()` — protects forms from CSRF attacks.
- `redirectTo()`, `jsonResponse()`, `setFlash()`, `getFlash()` — common web utilities.

### What is `core/dependencies.php`?
- Creates one shared instance of each model/controller.
- Returns them as an array so `public/index.php` and `api/` files can use them.

### What is PDO?
- PHP Data Objects — a safe way to connect to MySQL.
- Uses **prepared statements** to prevent SQL injection.

### What is `.env`?
- A config file that stores sensitive settings like the database password.
- Never committed to Git (listed in `.gitignore`).
- The app reads it automatically via `helpers.php`.

### User Roles
| Role | Access |
|---|---|
| **Manager** | Full access — users, products, reports, POs, etc. |
| **Supervisor** | Can manage inventory, view reports |
| **Salesman** | Can view products, record sales |
| **Logistic Handler** | Can manage delivery log and reorders |
