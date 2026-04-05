# AGENT.md — Inventra Inventory Management System

> This file is the canonical instruction set for all AI coding agents (Claude Code, Codex CLI,
> or any agentic tool) working on the Inventra codebase. Read this file in full before writing
> any code, modifying any file, or making any architectural decision.

---

## 1. Project Overview

**Inventra** is a web-based inventory management system built for a university project context.
It is a multi-role, full-stack application that tracks products, stock movements, purchase orders,
supplier logistics, sales reports, and AI-powered insights.

- **Version**: 4.0 (user stories document reference)
- **Roles**: Manager · Supervisor · Salesman · Logistic Handler
- **Bootstrap**: One Manager account is pre-seeded in the database at deployment. There is NO
  self-registration. All accounts are created by the Manager only.

---

## 2. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT (Browser)                         │
│  React SPA  ·  TailwindCSS  ·  Lucide Icons  ·  Material Icons  │
│  Chart.js (graphs)  ·  Leaflet.js (heat map)                    │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTPS / REST JSON
┌───────────────────────────▼─────────────────────────────────────┐
│                     BACKEND (Node.js / Express)                  │
│  Session Auth (express-session + bcrypt)                        │
│  Role-Based Access Control middleware (server-side enforced)    │
│  REST API  ·  File Upload (Multer → /uploads/products/)         │
│  AI Proxy (never exposes API key to client)                     │
│  Cache layer (in-memory or Redis) for dashboard totals          │
└───────────┬───────────────────────────────┬─────────────────────┘
            │  SQL (Knex / Prisma ORM)       │  AI API
┌───────────▼────────────┐    ┌─────────────▼──────────────┐
│   PostgreSQL / SQLite  │    │  Claude API                │
│   (see DB schema §4)   │    │  claude-sonnet-4-20250514  │
└────────────────────────┘    └────────────────────────────┘
```

### 2.1 Frontend Stack
| Concern | Choice |
|---------|--------|
| Framework | React (Vite) |
| Styling | TailwindCSS |
| Icons | Lucide React **+** Google Material Symbols (Outlined) |
| Charts | Chart.js |
| Maps | Leaflet.js |
| HTTP client | Axios or native fetch |
| Routing | React Router v6 |

### 2.2 Backend Stack
| Concern | Choice |
|---------|--------|
| Runtime | Node.js (≥ 18 LTS) |
| Framework | Express.js |
| Auth | express-session · bcryptjs |
| ORM | Prisma (preferred) or Knex |
| File Upload | Multer → `/uploads/products/` |
| Validation | Zod (server-side) |
| AI Proxy | Axios to Anthropic `/v1/messages` |
| Rate Limiting | express-rate-limit |

### 2.3 Module Map
```
src/
├── auth/           ← Login, logout, session, RBAC middleware
├── users/          ← Manager creates / edits / deactivates accounts
├── products/       ← CRUD, image upload, categories, search
├── stock/          ← Stock in, stock out, movement history
├── monitoring/     ← Dashboard, low-stock alerts, alert graph
├── reports/        ← Sales (monthly/daily), inventory, stock movement, CSV export
├── logistics/      ← Suppliers, purchase orders, shipment tracking, delivery log
└── ai/             ← Reorder prediction, sales insight, distribution heat map
```

---

## 3. Role Permissions Matrix

| Feature | Manager | Supervisor | Salesman | Logistic Handler |
|---------|:-------:|:----------:|:--------:|:----------------:|
| Login / Logout | ✅ | ✅ | ✅ | ✅ |
| Create staff accounts | ✅ | ❌ | ❌ | ❌ |
| Assign / manage roles | ✅ | ❌ | ❌ | ❌ |
| Edit / deactivate user | ✅ | ❌ | ❌ | ❌ |
| Add / edit product | ✅ | ❌ | ❌ | ❌ |
| Delete / archive product | ✅ | ❌ | ❌ | ❌ |
| View products & search | ✅ | ✅ | ✅ | ✅ |
| Upload product image | ✅ | ❌ | ❌ | ❌ |
| Add / edit / delete categories | ✅ | ❌ | ❌ | ❌ |
| View categories | ✅ | ✅ | ✅ | ✅ |
| Set min stock threshold | ✅ | ❌ | ❌ | ❌ |
| Log stock in | ✅ | ✅ | ❌ | ✅ |
| Log stock out | ✅ | ✅ | ✅ | ❌ |
| View stock levels | ✅ | ✅ | ✅ | ✅ |
| View stock movement history | ✅ | ✅ | ❌ | ✅ |
| View inventory dashboard | ✅ | ✅ | ✅ | ❌ |
| Low stock alerts | ✅ | ✅ | ✅ | ✅ |
| Low stock alert graph | ✅ | ✅ | ❌ | ❌ |
| Add / manage suppliers | ✅ | ❌ | ❌ | ❌ |
| Create purchase order | ✅ | ❌ | ❌ | ✅ |
| Receive purchase order | ✅ | ❌ | ❌ | ✅ |
| Track shipment | ✅ | ❌ | ❌ | ✅ |
| View PO tracker | ✅ | ❌ | ❌ | ✅ |
| View reorder suggestion list | ✅ | ✅ | ❌ | ✅ |
| View delivery log | ✅ | ❌ | ❌ | ✅ |
| View monthly sales report | ✅ | ✅ | ❌ | ❌ |
| View daily sales report | ✅ | ✅ | ✅ | ❌ |
| Date range filter on reports | ✅ | ✅ | ✅ | ❌ |
| Export reports to CSV | ✅ | ❌ | ❌ | ❌ |
| Low stock alert report | ✅ | ✅ | ✅ | ❌ |
| Stock movement report | ✅ | ✅ | ❌ | ❌ |
| Generate inventory report | ✅ | ❌ | ❌ | ❌ |
| AI sales insight card | ✅ | ❌ | ❌ | ❌ |
| AI reorder prediction | ✅ | ✅ | ❌ | ✅ |
| AI distribution heat map | ✅ | ❌ | ❌ | ❌ |

---

## 4. Database Schema

> Use Prisma migrations. Never mutate a Received PO or a movement history record.

```prisma
model User {
  id           Int       @id @default(autoincrement())
  fullName     String
  email        String    @unique
  passwordHash String
  role         Role
  isActive     Boolean   @default(true)
  createdAt    DateTime  @default(now())
  movements    StockMovement[]
}

enum Role {
  MANAGER
  SUPERVISOR
  SALESMAN
  LOGISTIC_HANDLER
}

model Category {
  id          Int       @id @default(autoincrement())
  name        String    @unique
  description String?
  createdAt   DateTime  @default(now())
  products    Product[]
}

model Supplier {
  id             Int       @id @default(autoincrement())
  name           String
  contactPerson  String?
  email          String
  phone          String?
  isActive       Boolean   @default(true)
  products       Product[]
  purchaseOrders PurchaseOrder[]
}

model Product {
  id            Int       @id @default(autoincrement())
  name          String
  sku           String    @unique
  description   String?
  imagePath     String?
  stockQuantity Int       @default(0)
  minThreshold  Int       @default(0)
  isArchived    Boolean   @default(false)
  categoryId    Int?
  supplierId    Int?
  createdAt     DateTime  @default(now())
  updatedAt     DateTime  @updatedAt
  category      Category? @relation(fields: [categoryId], references: [id])
  supplier      Supplier? @relation(fields: [supplierId], references: [id])
  movements     StockMovement[]
  poLineItems   POLineItem[]
}

model StockMovement {
  id         Int           @id @default(autoincrement())
  productId  Int
  userId     Int
  type       MovementType
  quantity   Int
  reason     String?
  createdAt  DateTime      @default(now())
  product    Product       @relation(fields: [productId], references: [id])
  user       User          @relation(fields: [userId], references: [id])
}

enum MovementType {
  IN
  OUT
  RETURN
  BULK_IN
}

model PurchaseOrder {
  id               Int       @id @default(autoincrement())
  poNumber         String    @unique
  supplierId       Int
  status           POStatus  @default(PENDING)
  expectedDate     DateTime?
  carrierName      String?
  trackingNumber   String?
  dispatchDate     DateTime?
  expectedArrival  DateTime?
  shipmentStatus   ShipmentStatus @default(ORDER_PLACED)
  statusUpdatedAt  DateTime?
  createdAt        DateTime  @default(now())
  supplier         Supplier  @relation(fields: [supplierId], references: [id])
  lineItems        POLineItem[]
  deliveryLog      DeliveryLog[]
}

enum POStatus {
  PENDING
  RECEIVED
}

enum ShipmentStatus {
  ORDER_PLACED
  DISPATCHED
  IN_TRANSIT
  DELIVERED
}

model POLineItem {
  id               Int           @id @default(autoincrement())
  poId             Int
  productId        Int
  quantityOrdered  Int
  quantityReceived Int?
  po               PurchaseOrder @relation(fields: [poId], references: [id])
  product          Product       @relation(fields: [productId], references: [id])
}

model DeliveryLog {
  id               Int           @id @default(autoincrement())
  poId             Int
  supplierId       Int
  productId        Int
  quantityOrdered  Int
  quantityReceived Int
  dateReceived     DateTime      @default(now())
  po               PurchaseOrder @relation(fields: [poId], references: [id])
}
```

---

## 5. API Endpoints Reference

### Auth
| Method | Path | Role | Description |
|--------|------|------|-------------|
| POST | `/api/auth/login` | All | Verify credentials, create session |
| POST | `/api/auth/logout` | All | Destroy session |

### Users
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/users` | Manager | List all users |
| POST | `/api/users` | Manager | Create staff account |
| PUT | `/api/users/:id` | Manager | Edit name/email/role |
| PATCH | `/api/users/:id/deactivate` | Manager | Deactivate account |

### Products
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/products` | All | List (with `?search=&category=&archived=`) |
| POST | `/api/products` | Manager | Create product |
| GET | `/api/products/:id` | All | Product detail |
| PUT | `/api/products/:id` | Manager | Edit product |
| DELETE | `/api/products/:id` | Manager | Permanently delete |
| PATCH | `/api/products/:id/archive` | Manager | Archive product |
| POST | `/api/products/:id/image` | Manager | Upload/replace image |
| GET | `/api/products/:id/movements` | Manager, Supervisor, LH | Movement history |
| GET | `/api/products/:id/reorder-prediction` | Manager, Supervisor, LH | AI reorder days |

### Categories
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/categories` | All | List categories |
| POST | `/api/categories` | Manager | Create category |
| PUT | `/api/categories/:id` | Manager | Edit category |
| DELETE | `/api/categories/:id` | Manager | Delete (rejects if products linked) |

### Stock
| Method | Path | Role | Description |
|--------|------|------|-------------|
| POST | `/api/stock/in` | Manager, Supervisor, LH | Log stock in |
| POST | `/api/stock/out` | Manager, Supervisor, Salesman | Log stock out |

### Dashboard & Monitoring
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/dashboard/summary` | Manager, Supervisor, Salesman | Cached KPI cards |
| GET | `/api/dashboard/alert-graph` | Manager, Supervisor | Stock vs threshold per product |
| GET | `/api/dashboard/activity` | Manager, Supervisor, Salesman | Recent stock events |

### Suppliers
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/suppliers` | Manager | List suppliers |
| POST | `/api/suppliers` | Manager | Create supplier |
| PUT | `/api/suppliers/:id` | Manager | Edit supplier |
| PATCH | `/api/suppliers/:id/deactivate` | Manager | Deactivate |

### Logistics
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/logistics/reorder-suggestions` | Manager, Supervisor, LH | Products below threshold |
| GET | `/api/logistics/delivery-log` | Manager, LH | Delivery log (filterable) |
| GET | `/api/purchase-orders` | Manager, LH | List POs (filter by status) |
| POST | `/api/purchase-orders` | Manager, LH | Create PO |
| GET | `/api/purchase-orders/:id` | Manager, LH | PO detail |
| PATCH | `/api/purchase-orders/:id/tracking` | Manager, LH | Update shipment tracking |
| PATCH | `/api/purchase-orders/:id/receive` | Manager, LH | Confirm PO receipt |

### Reports
| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/reports/inventory` | Manager | Inventory report (date range, CSV) |
| GET | `/api/reports/sales/monthly` | Manager, Supervisor | Monthly bar chart data |
| GET | `/api/reports/sales/daily` | Manager, Supervisor, Salesman | Daily line chart data |
| GET | `/api/reports/low-stock` | Manager, Supervisor, Salesman | Low stock report |
| GET | `/api/reports/stock-movement` | Manager, Supervisor | Movement summary |

### AI
| Method | Path | Role | Description |
|--------|------|------|-------------|
| POST | `/api/ai/sales-insight` | Manager | Proxy to Claude API; returns 2-3 sentence insight |
| POST | `/api/ai/distribution-insight` | Manager | Proxy region data to Claude API |

---

## 6. User Stories — Sprint Plan

### Sprint 1 — Week 1 (Foundation)
- US-AUTH-01 Manager creates staff account
- US-AUTH-02 User logs in
- US-AUTH-03 User logs out
- US-AUTH-04 Manager edits/deactivates account
- US-PM-01 Add product
- US-PM-02 Edit product
- US-PM-03 Delete/archive product
- US-PM-04 View product details
- US-PM-05 Search and filter products
- US-PM-06 Manage product categories
- US-PM-07 Upload product image
- US-LG-01 Add and manage suppliers

### Sprint 1 — Week 2 (Stock Core)
- US-SM-01 View current stock levels
- US-SM-02 Log stock in
- US-SM-03 Log stock out
- US-SM-04 Set minimum stock threshold
- US-LG-02 View reorder suggestion list
- US-LG-03 Create a purchase order

### Sprint 1 — Week 3 (Monitoring & PO Flow)
- US-MA-01 View inventory dashboard
- US-MA-02 Receive low stock alerts
- US-MA-03 View low stock alert graph
- US-MA-04 View stock movement history
- US-LG-05 Mark purchase order as received
- US-LG-06 View purchase order tracker
- US-LG-07 View delivery log

### Sprint 2 — Week 4 (Sales Reports)
- US-SR-01 View monthly sales report
- US-SR-02 View daily sales report
- US-SR-03 Filter sales reports by date range
- US-SR-04 Export sales report to CSV

### Sprint 2 — Week 5 (AI Features)
- US-AI-01 AI reorder prediction
- US-AI-02 AI sales insight card
- US-AI-03 AI product distribution heat map

### Sprint 2 — Week 6 (Remaining Reports)
- US-MA-05 Generate inventory report
- US-SR-05 View low stock alert report
- US-SR-06 View stock movement report
- US-LG-04 Track product shipment

---

## 7. UI Design Guide

### 7.1 Color Palette

All colour tokens must be defined as CSS custom properties and consumed via Tailwind's
`extend.colors`. Do not hardcode hex values in components.

```css
:root {
  /* Seed / Primary — deep navy blue */
  --color-primary:          #1E3A8A;   /* Tailwind: primary */
  --color-primary-light:    #2563EB;   /* hover / active states */
  --color-primary-dim:      #1E3A8A99; /* muted / 60% opacity */
  --color-primary-container:#DBEAFE;   /* backgrounds, pills */
  --color-on-primary:       #FFFFFF;

  /* Secondary — slate blue-grey */
  --color-secondary:        #475569;
  --color-secondary-container: #CBD5E1;
  --color-on-secondary:     #FFFFFF;

  /* Tertiary — near black */
  --color-tertiary:         #0F172A;
  --color-on-tertiary:      #F8FAFC;

  /* Neutral */
  --color-neutral:          #787677;
  --color-neutral-container:#F1F5F9;

  /* Surface tiers */
  --color-surface:               #FAFAFA;
  --color-surface-container-low: #F8FAFC;
  --color-surface-container:     #F1F5F9;
  --color-surface-container-high:#E2E8F0;
  --color-surface-container-highest: #CBD5E1;

  /* On-surface */
  --color-on-surface:        #0F172A;
  --color-on-surface-variant:#475569;

  /* Error */
  --color-error:            #BE123C;
  --color-error-container:  #FFE4E6;
  --color-on-error:         #FFFFFF;
  --color-on-error-container: #881337;

  /* Outline */
  --color-outline:          #94A3B8;
  --color-outline-variant:  #CBD5E1;

  /* Alert / Warning */
  --color-warning:          #B45309;
  --color-warning-container:#FEF3C7;
}
```

Tailwind config extension:
```js
// tailwind.config.js
extend: {
  colors: {
    primary:                  'var(--color-primary)',
    'primary-light':          'var(--color-primary-light)',
    'primary-container':      'var(--color-primary-container)',
    'on-primary':             'var(--color-on-primary)',
    secondary:                'var(--color-secondary)',
    'secondary-container':    'var(--color-secondary-container)',
    tertiary:                 'var(--color-tertiary)',
    'on-tertiary':            'var(--color-on-tertiary)',
    neutral:                  'var(--color-neutral)',
    surface:                  'var(--color-surface)',
    'surface-container':      'var(--color-surface-container)',
    'surface-container-low':  'var(--color-surface-container-low)',
    'surface-container-high': 'var(--color-surface-container-high)',
    'on-surface':             'var(--color-on-surface)',
    'on-surface-variant':     'var(--color-on-surface-variant)',
    error:                    'var(--color-error)',
    'error-container':        'var(--color-error-container)',
    'on-error':               'var(--color-on-error)',
    outline:                  'var(--color-outline)',
    'outline-variant':        'var(--color-outline-variant)',
    warning:                  'var(--color-warning)',
    'warning-container':      'var(--color-warning-container)',
  }
}
```

### 7.2 Typography

```css
font-family: 'Inter', sans-serif;   /* primary — all text */
```

| Role | Class | Weight | Size |
|------|-------|--------|------|
| Page title | `font-headline` | 900 (Black) | 3rem–3.5rem |
| Section heading | `font-bold` | 700 | 1.25rem |
| Label / meta | `text-xs font-bold uppercase tracking-widest` | 700 | 0.75rem |
| Body | `font-normal` | 400 | 0.875rem–1rem |
| Mono (SKU, ref) | `font-mono text-xs` | 400 | 0.75rem |

### 7.3 Icon System

Use **both** icon libraries. Never substitute one for the other arbitrarily.

**Lucide React** — used for actions, navigation, and UI chrome:
```jsx
import { Plus, Search, Bell, Settings, ChevronRight,
         PackageCheck, Truck, BarChart2, ShieldAlert,
         LogOut, UserCircle, Archive, Trash2, Edit,
         Download, Filter, RefreshCw, Eye, Boxes,
         ArrowDownToLine, ArrowUpFromLine } from 'lucide-react'
```

**Google Material Symbols (Outlined)** — used for product/domain icons within data displays,
cards, and table cells:
```html
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet"/>
<span class="material-symbols-outlined">inventory_2</span>
```

Common Material Symbols for this project:
`inventory_2` · `analytics` · `domain` · `local_shipping` · `precision_manufacturing` ·
`sensors` · `architecture` · `settings_input_component` · `token` · `hub` · `history_edu` ·
`warning` · `output` · `input`

### 7.4 Layout Structure

Every page follows this shell:

```
┌─────────── TopNavBar (fixed, h-16) ────────────────────────────┐
│  Logo  ·  Primary Nav Links  ·  Search  ·  Bell  ·  Settings  ·  Avatar │
└────────────────────────────────────────────────────────────────┘
┌── SideNav ──┐ ┌──────────── Main Content ──────────────────────┐
│ w-20 (icon) │ │  ml-20 pt-16 min-h-screen                      │
│ hover:w-64  │ │  max-w-[1600px] mx-auto px-10 py-12            │
│ (text label)│ │                                                  │
│  Inventory  │ │  <PageHeader>                                    │
│  Dashboard  │ │    eyebrow label · H1 · description             │
│  Analytics  │ │    action buttons (right-aligned)               │
│  Warehouses │ │  </PageHeader>                                   │
│  Logistics  │ │                                                  │
│             │ │  <ContentGrid>                                   │
│             │ │    12-column CSS grid with col-span variants     │
│             │ │  </ContentGrid>                                  │
└─────────────┘ └──────────────────────────────────────────────────┘
```

**TopNavBar**:
- `fixed top-0 w-full z-50`
- `bg-slate-50/70 backdrop-blur-md border-b border-slate-200/50`
- Active nav link: `text-primary font-semibold border-b-2 border-primary h-full flex items-center`

**SideNavBar**:
- `fixed left-0 top-0 h-full w-20 hover:w-64 transition-all duration-300 z-40`
- `bg-slate-50 border-r border-slate-200`
- Active item: `bg-blue-50 text-primary border-r-4 border-primary`
- Inactive item: `text-slate-400 hover:text-slate-900 hover:bg-slate-100`
- Labels: `opacity-0 group-hover:opacity-100 text-xs font-medium uppercase tracking-widest`

**Background texture** (optional, applied to `<main>`):
```css
.blueprint-grid {
  background-image:
    linear-gradient(to right, #CBD5E1 1px, transparent 1px),
    linear-gradient(to bottom, #CBD5E1 1px, transparent 1px);
  background-size: 40px 40px;
  opacity: 0.04;
}
```

### 7.5 Component Patterns

**Metric / KPI Card**
```jsx
<div className="bg-surface-container-lowest p-8 rounded-xl relative overflow-hidden">
  <span className="material-symbols-outlined text-primary text-3xl mb-4">token</span>
  <p className="text-xs font-bold uppercase tracking-widest text-outline mb-1">Label</p>
  <div className="text-6xl font-black tabular-nums text-on-surface">12,842</div>
  <span className="text-xs font-bold text-primary bg-primary-container px-3 py-1 rounded-full mt-4 inline-flex">+4.2%</span>
</div>
```

**Status Badge**
```jsx
// Stable / OK
<span className="inline-flex items-center gap-2 bg-secondary-container text-on-secondary-container px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-tighter">
  <span className="w-1.5 h-1.5 rounded-full bg-primary" />
  Stable Stock
</span>

// Low / Error
<span className="inline-flex items-center gap-2 bg-error-container text-on-error-container px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-tighter">
  <span className="w-1.5 h-1.5 rounded-full bg-error" />
  Low Stock
</span>
```

**Primary Action Button**
```jsx
<button className="bg-primary text-on-primary px-8 py-3 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-primary/20 hover:scale-[1.02] transition-transform">
  <Plus size={18} />
  Provision New Asset
</button>
```

**Secondary / Ghost Button**
```jsx
<button className="bg-surface-container-high px-6 py-3 rounded-xl font-medium flex items-center gap-2 hover:bg-outline-variant transition-colors">
  <Filter size={16} />
  Category Filter
</button>
```

**Table Row**
- Container: `bg-surface-container-lowest rounded-xl overflow-hidden`
- `<thead>` cells: `text-xs font-bold uppercase tracking-widest text-on-surface-variant`
- Row: `hover:bg-surface-container-low transition-colors`
- Divider: `divide-y divide-outline-variant/10`
- SKU cell: `font-mono text-xs text-outline`
- Quantity cell: `font-black tabular-nums`

**Ledger / History Entry**
```
[product name bold uppercase]  [+45 / -120 primary/error font-black]
[ref #INV-xxxxx text-xs]        [Inbound / Outbound label text-[0.6rem]]
[● timestamp text-[0.65rem]]    [WH: A-01 badge]
```

**Error / Alert Card**
```jsx
<div className="bg-error text-on-error p-8 rounded-xl">
  <span className="material-symbols-outlined text-4xl mb-4" style={{fontVariationSettings:"'FILL' 1"}}>warning</span>
  <span className="text-xs font-bold bg-white/20 px-3 py-1 rounded-full uppercase">Critical</span>
  <div className="text-3xl font-bold mt-6">12 Items Depleted</div>
</div>
```

**AI Insight Card**
```jsx
// Three states: loading | insight | error
<div className="bg-primary-container border-l-4 border-primary p-6 rounded-xl">
  <p className="text-xs font-bold uppercase tracking-widest text-primary mb-2">AI Insight</p>
  {loading ? <Spinner /> : insight ? <p className="text-on-surface leading-relaxed">{insight}</p> : <p className="text-outline">Insight unavailable</p>}
</div>
```

**Page Header Pattern**
```jsx
<header className="flex flex-col md:flex-row justify-between items-end gap-8 mb-16">
  <div>
    <span className="text-xs font-bold uppercase tracking-[0.3em] text-primary mb-4 block">System Core / Registry</span>
    <h1 className="text-[3.5rem] leading-[0.9] font-black tracking-tighter text-on-surface font-headline">Page Title</h1>
    <p className="text-on-surface-variant font-light text-xl max-w-lg mt-2">Subtitle description.</p>
  </div>
  <div className="flex gap-4">
    {/* action buttons */}
  </div>
</header>
```

### 7.6 Chart Conventions (Chart.js)

- **Bar chart** (stock alert graph): bars `#BE123C` if below threshold, `#1E3A8A` if at/above.
  Dashed red horizontal annotation line at threshold with "Min" label using `afterDraw` plugin.
- **Bar chart** (monthly sales): primary blue bars `#1E3A8A`, trend line overlay in `#2563EB`.
- **Line chart** (daily sales): `#1E3A8A` line, dashed `#475569` horizontal average line,
  peak day point annotated.
- Always set `responsive: true`, `maintainAspectRatio: false`, container height explicit.
- Grid lines: `rgba(148, 163, 184, 0.2)`.
- Font: `Inter`, size 11, color `#475569`.

### 7.7 Role-Specific Landing Pages

| Role | Landing Page |
|------|-------------|
| Manager | Full Inventory Dashboard |
| Supervisor | Inventory Dashboard |
| Salesman | Stock Out page |
| Logistic Handler | PO Tracker |

---

## 8. Guardrails & Best Practices

The agent **MUST** follow all rules in this section unconditionally.

### 8.1 Security

- **NEVER** hardcode API keys, passwords, or secrets in any source file. Use `.env` exclusively.
  Add `.env` to `.gitignore` on first commit.
- **NEVER** call the Claude AI API from the frontend. Always proxy through a backend endpoint.
  The `CLAUDE_API_KEY` must only exist server-side.
- All RBAC checks **must** be enforced server-side in middleware. Frontend role checks are
  UI-only convenience and cannot be the sole gatekeeper.
- Sessions: `express-session` with `httpOnly: true`, `secure: true` (in production),
  `sameSite: 'strict'`. Session secret in `.env`.
- Passwords: always hash with `bcryptjs` (saltRounds: 12). Never store or log plaintext.
- Error messages on failed login: always generic ("Invalid email or password"). Never reveal
  which field is wrong.
- Block deactivated users at the auth middleware level, not only on the login form.
- Validate and sanitise all inputs server-side with Zod. Client validation is supplementary.
- Rate-limit all AI proxy endpoints with `express-rate-limit`.
- Image uploads: validate MIME type and size (max 2MB, JPG/PNG/WEBP only) server-side via
  Multer's `fileFilter`. Do NOT trust the client-supplied content-type alone.
- Never commit the `/uploads/` folder to version control. Add to `.gitignore`.

### 8.2 Data Integrity

- Stock quantity **must never go below 0**. Enforce this atomically in the database transaction
  on every stock-out operation. If insufficient stock, reject with a 422 status.
- `StockMovement` records are **immutable** once created. No UPDATE or DELETE endpoint shall
  exist for movement history.
- A `PurchaseOrder` with status `RECEIVED` is **locked**. No further edits to its line items
  or status are permitted.
- Stock level updates on PO receipt must happen in a **database transaction** — update all
  product quantities and create the delivery log entry atomically. Roll back on any failure.
- A category **cannot** be deleted if any product is currently assigned to it.

### 8.3 Code Quality

- **TypeScript** is required on both frontend and backend. Do not use `any` type without
  explicit justification comment.
- All API route handlers must be typed with request/response interfaces.
- Every new module must include:
  - Input validation (Zod schema)
  - Error handling (try/catch with typed errors)
  - A brief JSDoc comment on exported functions
- No `console.log` in production code. Use a structured logger (e.g., `pino`) on the backend.
- Use `async/await` consistently. Do not mix Promise `.then()` chains with `await`.
- Run `eslint` and `prettier` before committing. CI must block merges with lint errors.

### 8.4 API Design

- All endpoints return JSON. Error responses follow:
  ```json
  { "error": "Human-readable message", "code": "MACHINE_READABLE_CODE" }
  ```
- Successful mutation responses (POST/PUT/PATCH) return the mutated resource.
- Use HTTP status codes correctly: 200 OK, 201 Created, 204 No Content, 400 Bad Request,
  401 Unauthorized, 403 Forbidden, 404 Not Found, 409 Conflict, 422 Unprocessable Entity,
  500 Internal Server Error.
- Paginate all list endpoints. Default page size: 25. Accept `?page=&limit=` query params.
- Use plural nouns for resource names: `/api/products`, `/api/users`, `/api/categories`.

### 8.5 Frontend Practices

- Component files: `PascalCase.tsx`. Utility files: `camelCase.ts`.
- No inline styles except where unavoidable for dynamic values (e.g. chart config). Use
  Tailwind classes.
- All form inputs must have associated `<label>` elements for accessibility.
- Loading, empty, and error states must be handled for every data-fetching component.
- The AI Insight card must have three explicit render states: `loading`, `success`, `error`.
  The `error` fallback must display "Insight unavailable".
- Never block the UI thread. All fetches are async with loading indicators.
- The browser back button must not allow navigation to protected pages after logout. Implement
  a `useEffect` on the auth context to redirect unauthenticated users.

### 8.6 File Structure Convention

```
/
├── client/                  # React frontend (Vite)
│   ├── src/
│   │   ├── components/      # Shared UI components
│   │   ├── pages/           # Route-level page components
│   │   ├── hooks/           # Custom React hooks
│   │   ├── context/         # AuthContext, etc.
│   │   ├── services/        # API call abstractions (Axios)
│   │   ├── types/           # Shared TypeScript types
│   │   └── utils/           # Pure helper functions
│   └── public/
├── server/                  # Express backend
│   ├── src/
│   │   ├── middleware/      # auth, rbac, upload, rateLimiter
│   │   ├── routes/          # One file per resource
│   │   ├── services/        # Business logic (no DB calls here)
│   │   ├── repositories/    # All Prisma/DB calls isolated here
│   │   ├── schemas/         # Zod validation schemas
│   │   ├── ai/              # Claude API proxy logic
│   │   └── utils/           # Logger, csvBuilder, poNumberGen
│   └── prisma/
│       ├── schema.prisma
│       └── migrations/
├── uploads/                 # GITIGNORED — product images land here
├── .env.example             # Template — commit this, not .env
└── .gitignore
```

### 8.7 Existing Flows — Build On, Don't Replace

If any of the following already exist and pass tests, extend them; do not rewrite from scratch:

- Session middleware chain (`authMiddleware.ts`)
- RBAC guard factory (`requireRole(...roles)`)
- Multer upload configuration (`uploadMiddleware.ts`)
- Prisma client singleton (`prismaClient.ts`)
- CSV builder utility (`csvBuilder.ts`)
- Dashboard cache invalidation hook (`invalidateDashboardCache()`)

Before adding a new endpoint or component, search the codebase for existing patterns that
solve the same concern. Prefer extending to duplicating.

### 8.8 Testing Requirements

- Unit tests required for:
  - AI reorder prediction formula (`avgDailyUsage`, `daysToReorder`, edge cases)
  - Stock-out floor validation (quantity ≤ current stock)
  - RBAC middleware (each role permutation)
  - PO number generation uniqueness
- Integration tests required for:
  - Full login → session → protected route flow
  - Stock in → movement history record creation
  - PO receive → stock level update → delivery log entry (transactional)
- Use `vitest` (frontend) and `jest` + `supertest` (backend).

### 8.9 Environment Variables Template

```env
# .env.example — copy to .env and fill in values
NODE_ENV=development
PORT=4000
DATABASE_URL=postgresql://user:password@localhost:5432/inventra
SESSION_SECRET=replace_with_long_random_string
CLAUDE_API_KEY=sk-ant-...
UPLOAD_DIR=./uploads/products
MAX_FILE_SIZE_BYTES=2097152
AI_RATE_LIMIT_WINDOW_MS=60000
AI_RATE_LIMIT_MAX_REQUESTS=10
```

---

## 9. AI Feature Implementation Notes

### Reorder Prediction (US-AI-01) — Pure Arithmetic, No ML
```
avgDailyUsage = totalStockOut(last 30 days) / 30
daysToReorder = (currentStock - minThreshold) / avgDailyUsage

Rules:
- If fewer than 3 stock-out events in last 30 days → return "Insufficient data"
- If avgDailyUsage = 0 → return "Insufficient data"
- Round daysToReorder DOWN to integer
- Recalculate on every new stock-out event
```

### Sales Insight Card (US-AI-02) — Claude API Proxy
```
POST /api/ai/sales-insight
Body: { monthlyTotals: [{ month: string, total: number }] }

Backend prompt template:
"You are a business analyst assistant. Given the following monthly sales data for an
inventory system, provide a 2-3 sentence plain English business insight identifying the
most notable trend, the best performing month, and one actionable recommendation.
Data: <JSON>. Reply with only the insight paragraph, no preamble."

Model: claude-sonnet-4-20250514
max_tokens: 200
```

### Distribution Heat Map Insight (US-AI-03) — Claude API Proxy
```
POST /api/ai/distribution-insight
Body: { topRegions: [...], bottomRegions: [...] }

Backend prompt template:
"You are a distribution analyst. Given regional sales data, write a 2-3 sentence insight
identifying the top-performing regions, the underperforming regions, and one actionable
recommendation. Reply with only the insight paragraph."

Model: claude-sonnet-4-20250514
max_tokens: 200
```

---

## 10. Seed Script

At deployment, run the seed script to create the bootstrap Manager account.
There is **no other way** to get into the system.

```ts
// prisma/seed.ts
import { PrismaClient } from '@prisma/client'
import bcrypt from 'bcryptjs'

const prisma = new PrismaClient()

async function main() {
  const hash = await bcrypt.hash(process.env.SEED_MANAGER_PASSWORD!, 12)
  await prisma.user.upsert({
    where: { email: process.env.SEED_MANAGER_EMAIL! },
    update: {},
    create: {
      fullName: 'System Manager',
      email: process.env.SEED_MANAGER_EMAIL!,
      passwordHash: hash,
      role: 'MANAGER',
      isActive: true,
    },
  })
  console.log('✅ Manager seed complete')
}

main().finally(() => prisma.$disconnect())
```

Add to `.env.example`:
```env
SEED_MANAGER_EMAIL=manager@inventra.local
SEED_MANAGER_PASSWORD=ChangeThisImmediately!
```

---

## 11. Quick-Start Checklist for the Agent

When starting a new task, the agent must:

1. **Read this file** (already done if you're here).
2. **Check the sprint plan** (§6) to confirm the feature belongs to the current sprint.
3. **Search for existing implementations** before creating new files (§8.7).
4. **Write Zod schema first**, then route handler, then service, then repository.
5. **Add RBAC guard** to every new route using the `requireRole()` factory.
6. **Write or update tests** for any business logic function (§8.8).
7. **Validate `.env.example`** is updated if new environment variables are introduced.
8. **Lint and type-check** before marking a task complete: `npm run lint && npm run typecheck`.

---

*Last updated: March 2026 — Version 4.0 user stories*
