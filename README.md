# BizStay — PG & Guest Management for Delhi NCR

BizStay runs one PG / co-living / PG + hotel building: rooms and beds, residents and KYC, monthly billing, rent collection, compliance and daily operations. It has a web admin panel (Laravel + Filament) and a phone app (Flutter) for managers and residents.

Configured for **Delhi, Gurugram, Noida, Greater Noida, Ghaziabad and Faridabad**: localities, electricity boards (BSES / TPDDL / DHBVN / NPCL / PVVNL), and the police tenant-verification portal for each city.

> One building per install. Run a separate install (or subdomain) for each building.

## Features

**Rooms & residents**
- Rooms, beds and sharing types. Bed status (vacant / occupied / reserved / maintenance) stays in sync with check-in and check-out.
- **Bed Board**: every floor, room and bed on one screen, with who is in each bed, who is on notice and who owes money.
- Residents with KYC (Aadhaar stored as a hash plus the last 4 digits only), company, emergency contact and blacklist.
- **Police verification tracking**: status, reference number, police station and dates. Residents still pending are flagged on the dashboard and in the menu.
- Inquiries pipeline (walk-in, WhatsApp, NoBroker, 99acres, MagicBricks, Justdial, college tie-ups…) with one-click convert-to-tenant.
- Leave logs with mess credit, notice period, and checkout settlement against the deposit.

**Billing & collections**
- Monthly invoices are generated automatically and safely: prorated rent, split meter readings, maintenance, mess credit and late fees. Re-running never bills twice.
- Hotel / short stays use nightly rates with **GST 2.0 slabs: 5% up to ₹7,500 per night, 18% above**. Residential PG rent carries no GST.
- Printed invoices are titled "Tax Invoice" only when GST is actually charged. They show amounts in Indian format (₹1,25,000) and in words, plus a **UPI QR code** that any UPI app can scan.
- **WhatsApp reminders**: one click opens WhatsApp with the due amount, UPI ID or payment link already typed in, and logs the reminder.
- **Online payments (optional)**: Razorpay Payment Links. A webhook posts the payment to the ledger automatically, and a repeated webhook call can never post it twice.
- **HRA rent receipts**: printable receipts for each financial year, for residents to give their employer. Includes the landlord PAN, with a warning when annual rent crosses ₹1 lakh.

**Reports (CSV, opens in Excel)**
- Rent roll, dues aging (current / 1–30 / 31–60 / 61–90 / 90+ days), collections register, GST register (CGST/SGST split, SAC 996311), expense register and police verification status.

**Operations**
- **Visitor register (gate log)**: in/out times, whom they met, overnight flag, and who is still inside.
- Complaints with SLA tracking (24h for high priority, 72h for others), expenses with receipts, and a cash P&L.
- **Public property page** at `/stay`: live bed availability by room type, amenities, WhatsApp and call buttons, and an enquiry form that creates a lead directly (spam-protected and rate-limited).

**Roles & security**
| Role | Can do |
|---|---|
| Owner / Admin | Everything, including staff logins and deleting ledger rows |
| Manager | Billing, expenses, reports, building settings |
| Staff / Warden | Residents, visitors, complaints, collecting rent. No P&L, expenses or settings |
| Tenant | Phone app only: their own bills, payments and complaints. Never the admin panel |

Staff can be disabled without being deleted, and disabling a login also signs it out of the app. API login is rate-limited.

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Open **http://127.0.0.1:8000/admin**

| Login | Email / mobile | Password |
|---|---|---|
| Owner | admin@mail.com | password |
| Manager | manager@mail.com | password |
| Warden | warden@mail.com | password |
| Tenant (app) | 9810000001 | password |

**Change every password before going live.**

### Go-live checklist
1. Building Profile (Settings): name, city and locality, UPI ID, GSTIN (only if you run hotel stays), owner name and PAN, WhatsApp number.
2. Enter your real rooms and rents, or edit the seeded ones.
3. Add staff under **Settings → Logins & Staff**. For each resident, use **Guests → More → Create app login**.
4. Schedule the cron job: `* * * * * php artisan schedule:run`. It flags overdue invoices, generates invoices on the cycle day, applies late fees and logs reminders.
5. Serve over HTTPS and back up the database daily.

### Online payments (optional)
Add your Razorpay keys to `.env`:
```
RAZORPAY_KEY_ID=rzp_live_xxx
RAZORPAY_KEY_SECRET=xxx
RAZORPAY_WEBHOOK_SECRET=xxx
```
In the Razorpay dashboard, add a webhook for the `payment_link.paid` event pointing to `https://your-domain/api/webhooks/razorpay`. Without keys, BizStay works offline: residents pay by UPI and the office records the UTR.

### MySQL (MAMP)
Set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`, then run `php artisan migrate --seed`.

## Tests

```bash
php artisan test
```

Covers billing (proration, idempotent invoices, partial payments, settlement), access control (tenant isolation, staff roles), Razorpay links and webhook, GST slabs, HRA receipts, reports, the public page and WhatsApp reminders.

## Phone app

The Flutter app is in `bizstay_app/`. Point it at your server URL and use HTTPS before releasing it on the stores.
