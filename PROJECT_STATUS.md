# BizStay — Where We Are & What's Remaining to Make It Best

> Updated: 22 Sep 2026 · Codebase: `/Applications/MAMP/htdocs/BizStay`
> Stack: Laravel 13 + Filament 3 + SQLite (MAMP/MySQL-ready) · Branch `main`

## 1. TL;DR — P0 FIXES LANDED 22 SEP 2026

**Admin UI restored 3/13 → 13/13. Health 6/10 → 8/10.**

| Layer | Status | Note |
|---|---|---|
| Database schema (13 biz tables) | DONE | Clean, indexed, idempotent keys |
| Models + 16 PHP enums | DONE | Type-safe, good relations/scopes |
| Domain services (5 singletons) | DONE | Best part — locking, proration, settlement |
| Observers (6) + cron schedule | DONE | Ledger self-maintains |
| Filament shell + dashboard (4 widgets) | DONE | Occupancy, dues, movements, floor chart |
| Filament Resources (CRUD UI) | FIXED 13/13 | All 10 missing Resource.php created; 40 admin routes |
| Seeders / Factories / Demo data | DONE | DatabaseSeeder + DemoSeeder + 12 factories; admin@bizstay.local |
| Tests | DONE (core) | BillingCoreTest 6/6 + 2 example = 8/8 green |
| Roles, tenant portal, gateway, reports | NOT STARTED | What separates good tool from best product |

**Health score: 8/10** — backend + navigable admin; money-maturity + portal remain.

## 1b. WHAT WAS FIXED 22 SEP 2026
- 10 Resources created (Guest/Booking/Inquiry/LeaveLog/UtilityMeter/MeterReading/Invoice/Payment/Expense/Complaint). CreateBooking→allocate(), bed-move→changeBed(), meter→record(), guest Aadhaar→hash-only.
- `Booking::outstandingDues()` + `Guest::outstandingBalance()` now use balance (total-paid), not total.
- Invoice idempotency UNIQUE crash fixed (whereDate lookup); checkout reuses month row as settlement.
- DemoSeeder: 6 rooms + meters + 5 guests via real service. 12 factories. BillingCoreTest 6/6.

## 2. What Is DONE (keep, don't rewrite)

### Module A — Property + Inventory: 90%
Files: `migrations/*000003*`, `Models/Property, Room, Bed`, `Resources/Property, Room, Bed`, `Widgets/Occupancy*`.
Works: singleton building profile with billing rules, sharing 1/2/3/4 auto-creates beds (`101-A/B`), bed state machine, `syncStatusFromBeds()` via observer, bed badge-stack view.
Polish left: room photos, floor-plan upload.

### Module B — Tenant & Stay: 85% backend, 10% UI
Files: `migrations/*000004*`, `Models/Guest, Booking, LeaveLog, Inquiry`, `Services/BedAllocationService` (FOR UPDATE lock, KYC + blacklist guard), `CheckoutSettlementService` (preview-settle-refund).
Works: Aadhaar hash-only, reusable guest, live scope, notice/withdraw/cancel, food opt-out days.
Broken: `Guest, Booking, LeaveLog, Inquiry` Resource.php files don't exist — no check-in/checkout UI.

### Module C — Utility & Metering: 80% backend, 0% UI
Files: `migrations/*000005*`, `Models/UtilityMeter, MeterReading`, `Services/MeterReadingService` (bulk floor entry, regression guard).
Works: 1 meter/utility/room, multiplier + rate override, `meter+date` unique, share divisor.
Broken: `UtilityMeter, MeterReading` Resource.php missing.

### Module D — Finance/Billing: 85% backend, 0% UI
Files: `Models/Invoice` (`BZ-YYYYMM-####`, booking+cycle unique), `Payment` (UTR unique), `Services/InvoiceService` (prorated rent + utility + maintenance - food + arrears), `routes/console.php` (00:30 overdue, 02:00 billing).
Broken: `Invoice, Payment, Expense` Resource.php missing. No PDF, no gateway. Late-fee % stored but never charged — verify.

### Module E — Operations: 70% backend, 0% UI
Files: `migrations/*000006*`, `Models/Complaint, Expense`.
Works: decoupled FKs, `open` scope, `resolve()` helper.
Broken: `ComplaintResource.php` missing. No SLA/photos/board.

### Platform: DONE
`AdminPanelProvider` (Property/Guests/Finance/Operations groups), `Dashboard`, 6 observers, 16 enums, FormRequests, domain exceptions.

## 3. What's BROKEN NOW (P0 — fix this week)

Root cause: 10 modules have `Pages/` folders but no parent `*Resource.php`. Filament `discoverResources()` only picks up top-level classes, so these pages never appear in nav.

```
app/Filament/Resources/
  OK: BedResource.php, PropertyResource.php, RoomResource.php
  MISSING: Booking, Guest, Inquiry, LeaveLog, Invoice,
           Payment, Expense, Complaint, UtilityMeter, MeterReading
```

Verify:
```bash
for f in Booking Complaint Expense Guest Inquiry Invoice LeaveLog MeterReading Payment UtilityMeter; do
  [ -f app/Filament/Resources/${f}Resource.php ] && echo "OK $f" || echo "MISSING ${f}Resource.php"
done
ls database/seeders 2>&1   # No such file — seeder gap
ls database/factories      # only UserFactory
find tests -type f         # only ExampleTest x2
```

Fix: create the 10 `*Resource.php` files reusing Bed/Room patterns. Every table action must call the existing services (BedAllocation, CheckoutSettlement, Invoice, MeterReading) — never inline DB writes. Estimate: 1-2 days + tests.


### P1 — Complete core PG loop (Weeks 2-3)
5. Inquiry-to-tenant: one-click Convert (Guest + allocate bed + first prorated invoice), overdue follow-up badge.
6. Check-in wizard: KYC verify/reject, bed picker (floor/sharing/AC), deposit auto-calc, first-cycle preview.
7. Notice & checkout UI: settlement preview modal (rent adjust, unbilled utility, food credit, damages, arrears -> refund/shortfall) + collect/refund buttons.
8. Money UI: invoice PDF + receipt, Mark Paid (method + UTR), overdue Remind, monthly collection report.
9. Meters UI: floor bulk-entry grid (`metersOnFloor()` exists), history, claimed-by-invoice link, anomaly flag (>2x avg).
10. Complaints board: kanban open/in_progress/resolved/closed, photo, assign staff, SLA timer.

### P2 — Money maturity + trust (Weeks 4-5)
11. Late-fee engine: apply `late_fee_percent` on overdue as visible line item (stored, never charged today).
12. Expense approvals + `receipt_path` preview, budget vs actual, monthly P&L widget (collected - expenses).
13. Deposit ledger per booking (collected -> applied -> refunded, never negative).
14. Exports: rent roll / dues aging / occupancy Excel; GST-ready invoice if needed.
15. Gateway: Razorpay/UPI intent + webhook -> auto Payment success -> invoice paid_at. Keep manual cash/UPI fallback.

### P3 — BEST layer (Weeks 6-8)
16. Roles + audit: `spatie/laravel-permission` (Owner/Manager/Accountant/Housekeeping) + activity log on money + bed moves. 2FA for owner.
17. Notifications: SMS/WhatsApp/Email on invoice raised, due-in-3-days, overdue, complaint resolved. Queue + log.
18. Tenant portal (2nd panel): my dues, pay online, raise complaint, leave/food opt-out, download invoices.
19. Public site: availability checker, room photos, inquiry form -> inquiries table.
20. Multi-property LAST: add `property_id` (additive per migration comment), property switcher. Keep singleton until then.
21. Ops polish: `spatie/laravel-backup`, health checks, login rate-limit, mobile-first tables.

## 5. Module Scorecard

| # | Module | Backend | UI | Gap to best |
|---|---|---|---|---|
| A | Property/Rooms/Beds | 90% | 90% | photos, floor plan |
| B1 | Guests + KYC | 85% | 0% | GuestResource, doc preview, duplicate-Aadhaar warn |
| B2 | Bookings / stay | 90% | 0% | BookingResource, wizard, settlement modal |
| B3 | Inquiries | 60% | 0% | InquiryResource, convert action, reminders |
| B4 | Leave / food | 70% | 0% | LeaveLogResource, approve flow |
| C | Meters/Readings | 80% | 0% | bulk grid, anomaly flag |
| D1 | Invoices | 85% | 0% | InvoiceResource, PDF, late-fee line |
| D2 | Payments | 80% | 0% | PaymentResource, gateway, refund flow |
| D3 | Expenses/P&L | 60% | 0% | ExpenseResource, approvals, receipts |
| E | Complaints | 70% | 0% | kanban, SLA, photos |
| X | Roles/Audit | 20% | 20% | Shield, log, 2FA |
| X | Notify/Reports/Portal | 0-10% | 0% | queue, exports, 2nd panel |

## 6. Suggested Next 5 Commits
1. `fix(admin): restore 10 missing *Resource.php + nav groups` DONE 22-Sep
2. `chore(db): DatabaseSeeder + 13 factories + demo admin` DONE 22-Sep
3. `test(core): proration + allocation lock + invoice idempotency + settlement` DONE 22-Sep
4. `feat(stay): inquiry convert + check-in wizard + checkout actions`
5. `feat(billing): invoice PDF + Mark Paid + overdue remind + P&L widget`

## 7. Risks / Decisions
1. Singleton vs multi-property: keep singleton until P3 (schema designed for it).
2. README overclaims (multi-property, 8 resource pages, seed data) — fix or implement; will fail a demo today.
3. ~~`Booking::outstandingDues()` sums `total_due` not balance — partial payments overstate dashboard dues. Fix in P1.~~ FIXED 22-Sep: now balance (total - paid).
4. Invoice has Edit but no Create page (correct — system-generated). Keep create disabled; add Generate-for-cycle header action.
5. Aadhaar hash-only is correct — never add clear-text column.

*If you only do one thing next: restore the 10 missing Resources. Seeders, tests, gateway, portal all depend on navigable admin.*

