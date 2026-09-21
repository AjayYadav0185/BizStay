# BizStay — PG Management System (Gurgaon)

BizStay is a Paying Guest (PG) management software built with **Laravel** and **Filament**, designed for Gurgaon-style operations where a manager/admin oversees multiple properties across Sectors, DLF Phases, Golf Course Road, etc.

## Features

- **PG Properties** — boys / girls / co-living properties with locality (Sector 44, DLF Phase 3, Golf Course Road…), amenities, deposit & notice-period rules, and live occupancy.
- **Rooms & Beds** — room-wise sharing (1/2/3/4), rent, AC/bathroom flags; beds are auto-created from sharing capacity and their status (vacant / occupied / blocked) stays in sync with tenant lifecycle.
- **Tenants** — full profile with KYC (Aadhaar/PAN/Passport/DL), company/occupation, emergency contact, rent, deposit, joining date, notice-period & vacate workflow (one-click actions).
- **Inquiries & Bookings** — lead pipeline from walk-ins and portals (NoBroker, 99acres, MagicBricks), follow-up dates, overdue follow-up filter, and **one-click Convert-to-Tenant** with bed allocation.
- **Payments** — rent (per month), deposits, utilities; pending/overdue tracking; quick "Mark Paid" with method & UTR reference; dashboard "Rent Due" widget with collect action.
- **Expenses** — electricity (DHBVN), water, internet, housekeeping, repairs, gas, salary per property.
- **Complaints** — categories (electrical, plumbing, food, security…), priorities, assignment to staff, resolve workflow.
- **Manager Dashboard** — occupancy %, active tenants, collected-vs-expected this month, pending dues, open complaints, new inquiries, property-wise bed-status chart, and due follow-ups.

## Tech Stack

- Laravel (PHP 8.3+)
- Filament 3 (admin panel, forms, tables, widgets)
- SQLite by default (zero-config) — easily switchable to MySQL/MAMP
- PHP 8.1 enums for all statuses (type-safe, auto-badged in Filament)

## Getting Started

```bash
composer install
cp .env.example .env         # if starting fresh
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Then open **http://127.0.0.1:8000/admin**

**Demo login:** `admin@bizstay.in` / `password`

## Using MySQL (MAMP) instead of SQLite

Uncomment and set in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bizstay
DB_USERNAME=root
DB_PASSWORD=root
```

Then run `php artisan migrate --seed`.

## Seeded Demo Data

- 3 properties: *BizStay Homes — Sector 44*, *BizStay Girls Nest — Sector 56*, *BizStay Comfort Stay — DLF Phase 3*
- 10 rooms / 24 beds, 8 tenants (companies around Cyber Hub, Udyog Vihar, Golf Course Road)
- Payments (deposits, last month paid, current month mixed pending/overdue)
- 4 inquiries, 6 expenses, 6 complaints

## Testing

```bash
php artisan test
```

Covers the manager dashboard widgets and all 8 Filament resource index pages.
