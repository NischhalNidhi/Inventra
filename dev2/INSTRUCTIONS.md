# Developer 2 (NN)

Owns authentication, session handling, staff accounts, and reporting.

Allowed areas:
- `dev2/controllers`
- `dev2/models`
- `dev2/views`
- `dev2/assets`

Responsibilities:
- User login
- Logout and session handling
- First-login password setup
- Manager-created staff accounts
- Inventory and sales report generation
- Forgot-password placeholder UI
- Removal of request-access feature

Do not modify:
- `dev1`, `dev3`, `dev4`
- Shared product/category logic
- Shared stock logic
- Purchase-order and logistics files outside coordinated work

Shared dependencies:
- `/core` for bootstrap/layout
- `/config` and `/database` for DB wiring

Boundary rule:
- Keep auth/report/user changes in `dev2`; shared entrypoint edits should remain thin and coordinated.
