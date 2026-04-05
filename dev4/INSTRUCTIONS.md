# Developer 4 (BP)

Owns stock operations from the Jira sprint.

Allowed areas:
- `dev4/controllers`
- `dev4/models`
- `dev4/views`
- `dev4/assets`

Responsibilities:
- Stock in
- Stock out
- Minimum stock threshold behavior
- Stock movement UI and validations

Do not modify:
- `dev1` product/category files
- `dev2` auth/report/user files
- `dev3` supplier/dashboard/search files
- Purchase-order/logistics files unless explicitly coordinated

Shared dependencies:
- `/core` for common bootstrap/layout
- `/config` and `/database` for DB access

Boundary rule:
- Keep stock changes in `dev4`; only touch shared wrappers when integration requires it.
