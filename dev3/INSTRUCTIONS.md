# Developer 3 (R)

Owns dashboard, suppliers, and product search/filter responsibilities from Jira.

Allowed areas:
- `dev3/controllers`
- `dev3/models`
- `dev3/views`
- `dev3/assets`

Responsibilities:
- Supplier management
- Dashboard inventory metrics
- Search and filter behavior for products
- Shared image-related assets only if the logic is reused beyond product forms

Do not modify:
- `dev1` product/category business logic
- `dev2` auth/report/user files
- `dev4` stock logic
- Purchase-order/logistics modules unless coordinated

Shared dependencies:
- `/core` for layout/bootstrap
- `/public/js` only when a search/filter integration cannot be isolated further

Boundary rule:
- Prefer feature-specific edits in `dev3`; coordinate before touching shared JS or bootstrap files.
