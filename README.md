# PharmaSys — Pharmaceutical Sales Management System

A comprehensive, Arabic-language pharmaceutical distribution and sales management platform built for field operations teams. PharmaSys streamlines invoice management, doctor commission tracking, zone-based expense analysis, warehouse inventory control, and financial reporting — all within a single, role-controlled web application.

---

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Usage](#usage)
- [Project Structure](#project-structure)
- [Database Design](#database-design)
- [Architecture Notes](#architecture-notes)
- [Future Improvements](#future-improvements)
- [License](#license)

---

## Description

PharmaSys solves a core operational challenge for pharmaceutical distribution companies: the complexity of managing multi-line sales, doctor commission deals, zone-level expenses, warehouse inventory, and consolidated financial reporting across multiple geographic areas.

Without a system like this, teams rely on spreadsheets that cannot enforce business rules, track commission deal targets, or flag financially risky zones in real time. PharmaSys replaces that manual overhead with an integrated, database-driven web application.

**Key value:**
- Automatic warehouse stock deduction on invoice creation
- Doctor deal target tracking tied directly to paid invoices
- Zone risk scoring (discount % + expenses vs. public price sales)
- Full CSV export on every major report for external analysis
- Role-based access control separating admin from accountant workflows

---

## Features

### Dashboard
- Monthly sales overview with selectable month/year filter
- Today's sales, monthly sales, and total outstanding receivables
- Top 5 pharmacists, doctors, and representatives by sales volume
- Risky zone alerts (zones where discount + expenses exceed 40% of public-price sales)
- Overdue invoices list (unpaid for > 3 months)
- High-discount pharmacist flagging (discount ≥ 51% on any invoice)
- Per-line dashboards (Line 1 / Line 2) with independent risk ratios

### Invoice Management
- Full CRUD with multi-item line entries per invoice
- Automatic zone/warehouse resolution from pharmacist → center → zone
- Drug-level discount per invoice line
- Payment status tracking: Paid (`1`), Deferred (`2`), Partial (`3`)
- Installment payment management (add, edit, delete payments on any invoice)
- PDF generation with Arabic text rendering (via DomPDF + ArPHP)
- CSV export with Arabic column headers

### Doctor Deal Management
- Configurable deals: fixed-target or open-ended (percentage-based)
- Deal-level pharmacist and drug scoping
- Automatic `achieved_amount` increment when a linked paid invoice is created
- Deal commission automatically recorded as a zone expense when paid
- Archive and toggle-active controls
- CSV export of deal details and associated invoices

### Warehouse Management
- Hierarchical warehouse model: main → sub-warehouses
- Stock add (from main to sub) and return (from sub back to main)
- Stock availability validated at invoice creation time

### Reporting
- **Pharmacist reports**: province → center → pharmacist drill-down with total sales, collected, and outstanding
- **Doctor reports**: province → center → doctor drill-down with commission earned, paid, and due
- **Representative reports**: sales by representative with per-line filtering
- **Zone risk report**: comprehensive zone-level risk ratio with export
- **Monthly financials**: income (collections) vs. expenses (zone costs + doctor commissions) with net cash flow and CSV export

### Master Data Management
- Provinces, Centers, Pharmacists, Doctors, Drugs, Representatives, Zones

### Access Control
- Laravel Breeze authentication
- Custom `CheckRole` middleware enforcing `admin` and `accountant` roles per route group

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 8.2 |
| **Framework** | Laravel 12 |
| **Frontend** | Blade templates, TailwindCSS, Vite |
| **Database** | MySQL |
| **Authentication** | Laravel Breeze |
| **PDF Generation** | `barryvdh/laravel-dompdf` |
| **Arabic Text** | `khaled.alshamaa/ar-php` |
| **Testing** | PestPHP, Laravel Sail |
| **Dev Tooling** | Laravel Pail, Laravel Pint, Concurrently |

---

## Installation

### Prerequisites

- PHP >= 8.2 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `xml`
- Composer
- Node.js >= 18 & npm
- MySQL

### Steps

**1. Clone the repository**

```bash
git clone <repository-url>
cd pharmasys
```

**2. Install PHP dependencies**

```bash
composer install
```

**3. Configure environment**

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pharmasys
DB_USERNAME=root
DB_PASSWORD=your_password
```

**4. Run database migrations**

```bash
php artisan migrate
```

**5. Seed initial data** *(if a seeder is available)*

```bash
php artisan db:seed
```

**6. Install frontend dependencies and build assets**

```bash
npm install
npm run build
```

**7. (Optional) Run everything with the Composer dev script**

The project ships with a `composer dev` script that starts the PHP server, queue listener, log watcher (Pail), and Vite dev server together:

```bash
composer dev
```

---

## Usage

### Starting the Development Server

```bash
# Start all services concurrently (recommended)
composer dev

# Or start individually
php artisan serve
npm run dev
```

Visit `http://localhost:8000`.

### Creating the First Admin User

Use Tinker to create an admin account:

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name'     => 'Admin',
    'email'    => 'admin@pharmasys.local',
    'password' => bcrypt('password'),
    'role'     => 'admin',
]);
```

### Running Tests

```bash
composer test
# or
php artisan test
```

### Generating a PDF Invoice

Navigate to any invoice detail page and click **Print PDF**. The system renders the Blade invoice template through DomPDF with full Arabic right-to-left text shaping via ArPHP.

### Exporting Reports to CSV

All major listing pages (Invoices, Deals, Zone Expenses, Monthly Financials, Deal Invoices) include an **Export to Excel** button that streams a UTF-8 BOM-encoded CSV directly to the browser.

---

## Project Structure

```
pharmasys/
├── app/
│   ├── Helpers/
│   │   └── genral.php                  # Global helper functions (autoloaded)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                  # All admin panel controllers
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── InvoiceController.php
│   │   │   │   ├── DoctorDealController.php
│   │   │   │   ├── WarehouseController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── ZoneController.php
│   │   │   │   ├── ZoneExpenseController.php
│   │   │   │   ├── ZoneReportController.php
│   │   │   │   ├── DoctorBalanceController.php
│   │   │   │   └── ... (CRUD controllers for master data)
│   │   │   └── Accountant/             # Accountant-role controllers (stub)
│   │   └── Middleware/
│   │       └── CheckRole.php           # Role-based access guard
│   └── Models/
│       ├── User.php                    # Auth user with role field
│       ├── Invoice.php                 # Core transaction model
│       ├── InvoiceDetail.php           # Per-drug invoice line
│       ├── InvoicePayment.php          # Installment payments
│       ├── Doctor.php                  # Doctor with prepaid risk attribute
│       ├── DoctorDeal.php              # Commission deal model
│       ├── Pharmacist.php
│       ├── Zone.php                    # Geographic zone with expenses
│       ├── ZoneExpense.php
│       ├── Warehouse.php               # Hierarchical warehouse model
│       ├── Drug.php
│       ├── Representative.php
│       ├── Province.php
│       └── Center.php
├── database/
│   ├── migrations/                     # 26 migration files (ordered)
│   └── seeders/
│       └── DatabaseSeeder.php
├── resources/
│   └── views/
│       ├── admin/                      # All admin Blade views
│       │   ├── dashboard.blade.php
│       │   ├── line_dashboard.blade.php
│       │   ├── invoices/
│       │   ├── deals/
│       │   ├── zones/
│       │   ├── warehouses/
│       │   ├── reports/
│       │   └── ...
│       ├── layouts/                    # Shared layout templates
│       └── components/                 # Reusable Blade components
├── routes/
│   ├── web.php                         # All named web routes
│   └── auth.php                        # Breeze auth routes
├── .env.example
├── composer.json
├── tailwind.config.js
└── vite.config.js
```

---

## Database Design

### Core Models and Relationships

```
Province ──< Center ──< Pharmacist ──< Invoice >──< Doctor
                │                          │
                └──< Zone >────────────────┘ (via center_zone pivot)
                      │
                      ├──< ZoneExpense
                      └── Warehouse (FK)

Doctor ──< DoctorDeal >──< Pharmacist   (deal_pharmacist pivot)
                    │──< Drug           (deal_drug pivot)
                    │──< Invoice        (doctor_deal_invoices pivot + contribution_amount)

Invoice ──< InvoiceDetail (drug_id, quantity, unit_price, discount%)
Invoice ──< InvoicePayment (partial payment tracking)

Warehouse ──< Zone
Warehouse (parent) ──< Warehouse (children)   [hierarchical]
Warehouse >──< Drug   (drug_warehouse pivot + quantity)
```

### Key Fields

| Model | Notable Fields |
|---|---|
| `Invoice` | `serial_number`, `line` (1/2), `status` (1=paid/2=deferred/3=partial), `total_amount`, `total_discount`, `final_total`, `paid_amount`, `remaining_amount` |
| `DoctorDeal` | `target_amount`, `achieved_amount`, `commission_percentage`, `commission_amount`, `is_active`, `is_archived`, `is_paid`, `is_prepaid` |
| `Zone` | `line` (1/2), `sales_representative_id`, `medical_representative_id`, `warehouse_id` |
| `User` | `role` (`admin` / `accountant`) |

---

## Architecture Notes

### Dual Product Lines
Every zone, invoice, and drug belongs to one of two product lines (`line = 1` or `line = 2`). Creating a zone automatically creates two sibling zone records — one per line — sharing the same centers but with independent representatives.

### Transaction Safety
All multi-step operations (invoice create/update/delete, stock transfers, deal payments) are wrapped in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()` blocks to guarantee atomicity.

### Warehouse Stock Lifecycle
```
Invoice Created  → deduct drug quantities from zone's warehouse
Invoice Updated  → restore old quantities, then deduct new quantities
Invoice Deleted  → restore all quantities back to warehouse
Stock Transfer   → sub-warehouse add deducts from parent; sub-warehouse return restores to parent
```

### Doctor Deal Impact Engine
The `processDoctorDealImpact()` method in `InvoiceController` is called whenever an invoice transitions to fully-paid status. It:
1. Finds active deals linked to the invoice's doctors and pharmacist
2. Calculates each deal's contribution from matching drug line items
3. Increments `achieved_amount` on the deal and records the invoice in the `doctor_deal_invoices` pivot
4. Reverses all of this on invoice deletion or payment reversal

### Zone Risk Score
```
Risk Ratio = ((total_discount + total_zone_expenses) / public_price_total) × 100
```
Zones exceeding 40% are flagged as "risky" on the main dashboard. This calculation is done in a single optimized DB query grouping invoice details and expense records by zone ID.

### CSV Export Pattern
All exports use Laravel's `response()->stream()` with a UTF-8 BOM prefix (`\xEF\xBB\xBF`) to ensure Arabic text renders correctly when opened in Microsoft Excel.

### Role-Based Access
The custom `CheckRole` middleware reads `Auth::user()->role` and enforces it at the route group level. The admin group (`/admin/*`) requires `role:admin`; the accountant group (`/accountant/*`) requires `role:accountant`.

---

## Future Improvements

- **Accountant module**: The `accountant` route group and controller namespace exist but are empty — the natural next step is implementing accountant-specific views for collections and payment management
- **Notifications**: Real-time alerts (via Laravel Echo / Pusher) for overdue invoices or deals nearing their target
- **Drug return management**: Currently stock returns are handled at the warehouse level; a formal customer-return invoice flow would add traceability
- **API layer**: Adding a JSON API (Laravel Sanctum) would enable a mobile field app for representatives to log invoices on-site
- **Report date range filters**: Most reports are currently filtered by month; custom date range filters would increase flexibility
- **Automated commission settlement**: A scheduled job to flag and notify when deal commission payments become overdue
- **Audit log**: Tracking who created or modified each invoice/deal for accountability in multi-user environments

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit your changes following PSR-12 coding standards (enforced by Laravel Pint)
4. Run tests: `composer test`
5. Open a pull request with a clear description of the change

---

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).
