# AGENT.md — Inventra v2 Improvement Agent

## Mission

You are an autonomous software improvement agent for **Inventra**, a production inventory management system built with vanilla PHP (no framework), MySQL via PDO, and vanilla JS/HTML/CSS. Your goal is to systematically improve the existing codebase into a hardened, performant, and polished v2 — without rewriting what already works.

**Guiding principle:** Build on top of existing working flows. Preserve all current logic, routes, models, and controller patterns. Improve, extend, and harden — never replace wholesale.

---

## Project Context

### Tech stack (do not change)
- Backend: Vanilla PHP, no framework
- Database: MySQL accessed via PDO
- Frontend: Vanilla JS, HTML5, vanilla CSS
- Architecture: Custom MVC, Front Controller pattern via `public/index.php`
- Auth: Session-based with RBAC (`Admin`, `Manager`, `Staff`)

### Entry point flow (preserve this)
1. Every request enters through `public/index.php`
2. `core/dependencies.php` bootstraps singletons and DB connection
3. Auth gate checks `isLoggedIn()` — unauthenticated users see `views/auth/index.php`
4. POST requests are intercepted before routing, CSRF validated, then dispatched via action switch
5. GET requests go through a page routing switch
6. Controllers handle business logic; Models handle DB; Views handle HTML output
7. PRG pattern: every POST ends in `setFlash()` + `redirectTo()`

---

## Improvement Domains

Work through these domains in order. Each domain lists specific tasks. Do not skip tasks or reorder domains without explicit instruction.

---

### Domain 1 — Security Hardening

**Priority: CRITICAL. Complete this domain before any other.**

#### 1.1 Session fixation protection
- File: `controllers/authController.php`, login success block
- Add `session_regenerate_id(true)` immediately before writing any user data to `$_SESSION`
- Confirm it is called on every successful login path, including OAuth or SSO paths if added later

#### 1.2 Secure cookie flags
- File: `public/index.php`, before `session_start()`
- Add `session_set_cookie_params()` with:
  - `httponly: true`
  - `samesite: 'Strict'`
  - `secure: ($_ENV['APP_ENV'] === 'production')` — false in local dev
  - `lifetime: 0` (session cookie)
- Store `APP_ENV` in `/config/.env` and load it via a `loadEnv()` helper

#### 1.3 Brute-force / rate limiting on login
- Create migration: `database/migrations/004_create_login_attempts.sql`
  - Table: `login_attempts(id, ip VARCHAR(45), attempted_at DATETIME)`
  - Index on `(ip, attempted_at)`
- File: `controllers/authController.php`
  - Add `checkRateLimit(string $ip): void` — query last 5 minutes, abort if >= 10 attempts
  - Add `recordAttempt(string $ip): void` — insert on every failed login
  - Add nightly cleanup: document a cron entry `0 2 * * * DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)`

#### 1.4 Security response headers
- File: `public/index.php`, top of file before any output
- Add via `header()`:
  - `Content-Security-Policy` — `default-src 'self'`, `script-src 'self'`, `style-src 'self' 'unsafe-inline'`, `img-src 'self' data:`, `frame-ancestors 'none'`, `base-uri 'self'`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: same-origin`
- Start with `Content-Security-Policy-Report-Only` in staging to identify violations before enforcing

#### 1.5 Upload directory execution lock
- Create `public/uploads/.htaccess` (Apache) with:
  - `Options -ExecCGI`
  - `php_flag engine off`
  - `RemoveHandler .php`
  - `ForceType application/octet-stream`
- Document the equivalent Nginx `location` block in `docs/nginx-config.md`

#### 1.6 HttpOnly + SameSite audit
- Grep codebase for any `setcookie()` calls outside `session_set_cookie_params` and add flags

---

### Domain 2 — Performance & Caching

#### 2.1 Paginate all `getAll()` queries
- Files: `models/Product.php`, `models/Supplier.php`, `models/Category.php`, `models/PurchaseOrder.php`
- Change signatures to `getAll(int $page = 1, int $perPage = 25, string $search = ''): array`
- Use `LIMIT :limit OFFSET :offset` with bound parameters
- Return `['data' => [...], 'total' => int]`
- Update all views to render pagination controls (previous/next + page numbers)
- Preserve existing `getAll()` behaviour by defaulting `$perPage = 25`

#### 2.2 Cache dashboard stats
- File: `models/Product.php` → `getDashboardStats()`
- Wrap with APCu if available: `apcu_fetch('dashboard_stats', $found)` — on miss, run query and `apcu_store('dashboard_stats', $result, 60)`
- Fall back gracefully to direct DB query if APCu is not installed
- Add cache-bust on any product create/update/delete in `productController.php`

#### 2.3 Static asset cache headers
- Document in `docs/webserver-config.md`: set `Cache-Control: public, max-age=31536000, immutable` on `/css/` and `/js/`
- Implement cache-busting via query string: `<script src="/js/main.js?v=<?= filemtime(...)?>">` in the base layout view

#### 2.4 Expensive report guards
- File: `models/Report.php`
- Before any report query, run a `SELECT COUNT(*)` estimate
- If row count exceeds 50,000, flash a warning and require the user to confirm export
- Log large report requests to a `report_log` table (user_id, report_type, row_count, executed_at)

---

### Domain 3 — UX & Frontend Polish

#### 3.1 Old input repopulation on validation failure
- File: `core/helpers.php` — add `flashOldInput(array $input): void` and `getOldInput(): array`
  - `flashOldInput`: unset `csrf_token` and file fields, store remainder in `$_SESSION['_old_input']`
  - `getOldInput`: read, unset (consume once), return
- Update all controller validation failure paths to call `flashOldInput($_POST)` before `redirectTo()`
- Update all form views to call `$old = getOldInput()` at top and use `$old['field'] ?? ''` for every `value=` attribute and `selected`/`checked` states on dropdowns and checkboxes

#### 3.2 Client-side validation
- File: `public/js/form-validate.js` (new)
- Mirror server-side rules:
  - Name: min 3 characters
  - SKU: `/^[A-Z0-9-]{4,30}$/`
  - Unit price / stock: numeric, >= 0
- Validate on `blur` per field; re-validate all on submit
- Block form submission if any field is invalid; do not rely solely on this — server validation remains authoritative
- Wrap each form field in `<div class="field">` with a `<span class="field-error"></span>` sibling
- Add `data-validate` attribute to forms that should be validated
- CSS: `.invalid` border in danger colour, `.field-error` in 12px danger text

#### 3.3 AJAX search loading states
- File: `public/js/search.js` (update existing)
- Add 280ms debounce on `input` event
- Show skeleton shimmer rows in `<tbody>` while request is in-flight
- Add `.searching` class to input (spinner background-image via CSS)
- Handle network errors gracefully — show "Search failed. Please retry." row
- Formalise the search endpoint: `?api=1&action=search_products&q=` returning `{"rows": [...]}` JSON
- Add equivalent endpoints for suppliers, categories

#### 3.4 Bulk select + action toolbar
- Files: `views/products/index.php`, `public/js/bulk.js` (new), `controllers/productController.php`
- Add checkbox column to product table (header checkbox = select all)
- Floating toolbar appears (flexbox, sticky bottom) when 1+ rows selected; shows count + action buttons
- Actions: Delete selected (with confirm dialog), Export selected to CSV
- Controller: `handleBulkDelete()` — validate IDs as comma-separated positive integers via `ctype_digit`, use PDO with placeholders, check `products.delete` permission
- Controller: `handleBulkExport()` — stream CSV with `Content-Disposition: attachment`
- Extend the toolbar pattern to Suppliers and PurchaseOrders tables

#### 3.5 Inline flash toast positioning
- Ensure existing toast notifications auto-dismiss after 4 seconds
- Add a CSS slide-in animation on appearance
- Stack multiple toasts if more than one flash message exists

---

### Domain 4 — Developer Experience

#### 4.1 Composer autoloader + PSR-4
- Initialise Composer: `composer init` in project root
- Define PSR-4 autoload in `composer.json`:
  ```json
  "autoload": {
    "psr-4": {
      "Inventra\\": "src/"
    }
  }
  ```
- Migrate classes gradually: start with Models, then Controllers
- Replace all `require_once` includes with autoloader; keep a compatibility shim during migration
- Do not introduce any framework dependency — Composer is for autoloading only

#### 4.2 Environment configuration
- Create `config/.env.example` with all required keys: `APP_ENV`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_SECRET`
- Create `core/loadEnv.php` — simple line-by-line parser, no external library
- Add `config/.env` to `.gitignore`
- Replace all hardcoded DB credentials in `core/dependencies.php` with `$_ENV` reads

#### 4.3 Structured error logging
- File: `core/logger.php` — lightweight PSR-3-inspired logger writing to `storage/logs/app-YYYY-MM-DD.log`
- Log levels: `error`, `warning`, `info`
- Auto-rotate: keep last 14 log files, delete older on each write
- Log every uncaught exception, every failed login attempt, every bulk action, every large report execution
- In production, suppress raw error output; log to file only (`display_errors = 0`)

#### 4.4 Docker local development
- Create `docker-compose.yml` at project root with services: `php` (php:8.2-apache), `db` (mysql:8.0), `phpmyadmin`
- Create `docker/php/Dockerfile` enabling PDO, `pdo_mysql`, `apcu`, `mbstring`
- Create `docker/mysql/init.sql` that imports all migration files in order
- Document in `README.md`: `docker compose up -d` → visit `http://localhost:8080`

#### 4.5 Basic test suite
- Install PHPUnit via Composer dev dependency
- Create `tests/Unit/ProductValidatorTest.php` — test SKU regex, name length, price range
- Create `tests/Unit/AuthRateLimitTest.php` — test attempt counting and lockout logic
- Create `tests/Integration/ProductModelTest.php` — test `create`, `findById`, `skuExists` against a test DB
- Add `composer test` script: `phpunit --testdox`
- Tests must pass before any PR is merged

---

### Domain 5 — API Layer

#### 5.1 JSON API response mode
- File: `public/index.php` — detect `$_GET['api'] === '1'` before the page routing switch
- For API requests: set `Content-Type: application/json`, wrap controller output in `{"success": true, "data": ...}` or `{"success": false, "error": "..."}`, output and exit
- Return correct HTTP status codes: 200 OK, 201 Created, 400 Bad Request, 401 Unauthorised, 403 Forbidden, 404 Not Found, 422 Unprocessable Entity
- Reuse existing controllers — add an `$isApi` flag that switches between JSON response and flash+redirect

#### 5.2 Formalise existing AJAX endpoints
- Document all AJAX endpoints in `docs/api.md`
- Endpoints to formalise:
  - `GET ?api=1&action=search_products&q=` → `{"rows": [{id, name, sku, stock, unit_price}]}`
  - `GET ?api=1&action=search_suppliers&q=` → `{"rows": [...]}`
  - `GET ?api=1&action=get_product&id=` → `{"data": {...}}`
  - `GET ?api=1&action=dashboard_stats` → `{"data": {...}}`
- All API requests must pass the existing session auth gate
- Add `api.view` permission check for all read endpoints

#### 5.3 Versioning
- Prefix all API routes with `v=1` parameter for future compatibility
- Document breaking-change policy in `docs/api.md`

---

### Domain 6 — Architecture Evolution

#### 6.1 Lightweight router
- File: `core/Router.php` (new)
- Replace the `switch ($page)` block in `index.php` with a simple array-based router:
  ```php
  $routes = [
    'products'  => ['controller' => $productController, 'method' => 'index', 'permission' => 'products.view'],
    'new-entry' => ['controller' => $productController, 'method' => 'create', 'permission' => 'products.create'],
    // ...
  ];
  ```
- Keep the router thin — no regex, no DI container changes, no framework features
- POST action dispatch remains separate from GET page routing

#### 6.2 Lazy dependency instantiation
- File: `core/dependencies.php`
- Wrap non-auth dependencies in closures; only instantiate when first accessed
- Prevents instantiating `$reportModel` on a simple product list page

#### 6.3 Background job groundwork
- Create `core/JobQueue.php` — DB-backed job queue table (`jobs(id, type, payload JSON, status, created_at, run_at)`)
- Create `bin/worker.php` — CLI script that polls the queue and dispatches jobs
- Use cases to queue immediately: large CSV exports, low-stock notification emails
- Document cron setup: `* * * * * php /var/www/html/bin/worker.php >> storage/logs/worker.log 2>&1`

---

### Domain 7 — New Features

These build on the improved foundation above.

#### 7.1 Low-stock notifications
- Use the job queue from Domain 6.3
- After every stock-decrementing action (purchase order received, manual adjustment), check `getLowStockProducts()`
- If any product is at or below `minimum_threshold`, enqueue a `notify_low_stock` job
- Worker dispatches an in-app notification (flash on next admin login) and optionally an email via `mail()`

#### 7.2 Audit log
- Create migration: `database/migrations/005_create_audit_log.sql`
  - Table: `audit_log(id, user_id, action, entity_type, entity_id, old_values JSON, new_values JSON, ip, created_at)`
- Create `core/AuditLogger.php` — static `log(string $action, string $entity, int $id, array $old, array $new): void`
- Call from controllers on every create, update, delete action
- Add `views/audit/index.php` — admin-only table showing recent log entries, filterable by user/action/entity

#### 7.3 CSV / Excel export for all data tables
- Add export button to Products, Suppliers, PurchaseOrders list views
- Controller method streams CSV with correct headers, no temp file
- Respect current filters (search query, date range) when exporting
- Bulk export (selected rows only) via the bulk toolbar from Domain 3.4

#### 7.4 Purchase order status workflow
- Extend PO statuses: `Draft → Submitted → Approved → Received → Cancelled`
- Add `views/purchase_orders/workflow.php` — visual status stepper
- On status `Received`: automatically increment product stock for each line item
- Role guard: only `Admin` and `Manager` can Approve; `Staff` can Submit and mark Received

#### 7.5 Dashboard improvements
- Add a sparkline chart (vanilla JS canvas, no library) for stock movement over last 30 days
- Add top-5 low-stock products widget
- Add recent activity feed pulling from the audit log
- Make stat cards clickable — link to the filtered list view

---

## Guardrails & Engineering Standards

These rules are non-negotiable. Every change you make must comply.

### Code quality
- All user input touching the DB must use PDO prepared statements with bound parameters. No string interpolation in SQL ever.
- All output to HTML must pass through `htmlspecialchars()`. No raw `echo $_POST[...]` or `echo $row[...]` in views.
- All uploaded files must be validated via `mime_content_type()`, not extension alone. Rename with `bin2hex(random_bytes(12))`.
- No business logic in views. Views only render variables passed to them.
- No DB queries in views. All data is fetched in the routing switch and passed to the view.

### Security
- Every POST action must verify `csrf_token` against `$_SESSION['csrf_token']` before any processing.
- Every page route and every action must call `$authController->authorize('permission.string')` as its first statement.
- Never expose stack traces or raw error messages in production (`APP_ENV=production` suppresses them).
- Never log passwords, tokens, or full credit card numbers. Redact sensitive fields before logging.
- Session ID must be regenerated on login and on privilege escalation.

### PRG pattern
- Every POST action must end with `setFlash()` + `redirectTo()`. No HTML output after a POST.
- Never use `header('Location: ...')` directly — always use the `redirectTo()` helper.

### File & directory safety
- The document root is `public/`. Config files, `.env`, migrations, and logs must never be inside `public/`.
- Uploaded files land in `public/uploads/` with execution disabled (Domain 1.5).
- Log files land in `storage/logs/` which must be outside the webroot or protected by `.htaccess`.

### Testing
- Every new model method must have at least one unit test.
- Every new validation rule must have a test case for the passing case and the failing case.
- Run `composer test` before completing any domain. All tests must pass.

### Git hygiene
- One commit per task (e.g. "feat: add session_regenerate_id on login").
- Commit messages follow Conventional Commits: `feat:`, `fix:`, `security:`, `refactor:`, `test:`, `docs:`.
- Never commit `config/.env`, `vendor/`, `storage/logs/`, or `node_modules/`.
- Each domain should be completed on its own branch: `v2/domain-1-security`, `v2/domain-2-performance`, etc.

### Backwards compatibility
- Do not rename or remove existing public methods on Models or Controllers without a deprecation comment first.
- Do not change existing URL route names (`?page=products`, etc.) — these may be bookmarked or linked externally.
- Database migrations must be additive only (new tables, new columns). Never drop or rename a column without a migration version gate.
- Keep the PRG redirect targets identical to v1 so existing bookmarks continue to work.

### Performance budget
- No page should execute more than 10 DB queries (use the logger to count in dev mode).
- No `getAll()` without pagination — never load unbounded result sets.
- Dashboard page must load in under 1 second on a modest VPS (APCu cache must be warm).

---

## Execution Order

Complete domains strictly in this sequence. Do not begin a later domain while any task in an earlier domain is incomplete.

```
Domain 1 — Security Hardening        ← start here, highest risk
Domain 2 — Performance & Caching
Domain 3 — UX & Frontend Polish
Domain 4 — Developer Experience
Domain 5 — API Layer
Domain 6 — Architecture Evolution
Domain 7 — New Features              ← only after all above are done
```

Within each domain, complete tasks top to bottom.

---

## Definition of Done

A domain is complete when:
- [ ] All tasks in the domain are implemented
- [ ] No existing functionality is broken (manual smoke test: login, add product, create PO, view dashboard)
- [ ] `composer test` passes with no failures
- [ ] No raw SQL string interpolation introduced
- [ ] No unescaped output introduced
- [ ] CSRF check present on all new POST actions
- [ ] `authorize()` called on all new routes and actions
- [ ] Changes committed with conventional commit messages on the domain branch

---

## File Map (quick reference)

```
public/
  index.php          ← entry point, routing, POST dispatch, security headers
  css/               ← stylesheets
  js/
    form-validate.js ← new: client-side validation
    bulk.js          ← new: bulk select
    search.js        ← update: debounce + skeletons
  uploads/
    .htaccess        ← new: block execution

core/
  dependencies.php   ← DI container (add lazy init)
  helpers.php        ← add flashOldInput, getOldInput, loadEnv
  Router.php         ← new: array-based router
  logger.php         ← new: structured file logger
  AuditLogger.php    ← new: audit trail
  JobQueue.php       ← new: background jobs

controllers/
  authController.php ← add rate limit, session regen
  productController.php ← add bulk actions, cache bust

models/
  Product.php        ← add pagination, APCu cache
  Report.php         ← add row count guard

views/
  products/
    form.php         ← add old input, data-validate, field wrappers
    index.php        ← add bulk toolbar, pagination
  audit/
    index.php        ← new

config/
  .env.example       ← new
  .env               ← gitignored

database/migrations/
  004_create_login_attempts.sql   ← new
  005_create_audit_log.sql        ← new
  006_create_jobs.sql             ← new

storage/logs/        ← gitignored, outside webroot if possible

tests/
  Unit/
    ProductValidatorTest.php
    AuthRateLimitTest.php
  Integration/
    ProductModelTest.php

docs/
  api.md
  nginx-config.md
  webserver-config.md

docker-compose.yml   ← new
docker/php/Dockerfile
docker/mysql/init.sql
bin/worker.php       ← new: job queue worker
```

---

## Notes for the Agent

- When in doubt, read the existing code before writing new code. Understand the current pattern, then extend it.
- If a task conflicts with an existing guardrail, stop and surface the conflict rather than proceeding.
- If a task requires a DB migration, write the migration SQL file first, then implement the PHP.
- If a task touches authentication or session handling, re-run the full auth smoke test after the change.
- Prefer small, focused commits over large multi-file changes.
- Ask for clarification before making any architectural decision not covered by this document.
