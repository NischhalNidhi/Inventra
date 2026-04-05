# Merge Guide

## Merge Order
1. `dev2` authentication/session/report changes
2. `dev1` product/category changes
3. `dev3` supplier/dashboard/search changes
4. `dev4` stock changes

## Ownership
- `dev1`: products, categories, product NPR price, product-form image flow
- `dev2`: auth, sessions, staff accounts, reports, forgot-password placeholder, request-access removal
- `dev3`: dashboard, suppliers, product search/filter, shared image helpers only when truly cross-module
- `dev4`: stock adjustments and minimum-threshold logic

## Shared Files
These are integration files and should stay thin:
- `core/*`
- `public/index.php`
- `api/*.php`
- `config/*`
- `database/*`
- `public/js/*`
- `public/css/*`

## Conflict Avoidance Rules
- Make feature logic changes inside the assigned `devX` folder first.
- Do not move another developer’s files without coordination.
- Keep shared wrapper edits minimal and focused on wiring.
- If a shared schema change is required, document it in the feature branch and rebase other branches after it lands.
- Avoid mixing structural refactors with unrelated logic changes.

## Notes
- Purchase-order and logistics modules remain shared/non-Jira-assigned in this refactor and should be coordinated separately.
- Uploads and runtime storage paths remain shared resources.
