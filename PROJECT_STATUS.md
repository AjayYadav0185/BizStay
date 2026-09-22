# BizStay — Where We Are & What's Remaining to Make It Best

> Updated: 22 Sep 2026 · Codebase: `/Applications/MAMP/htdocs/BizStay`
> Stack: Laravel 13 + Filament 3 + SQLite (MAMP/MySQL-ready) · Branch `main`

## 1. TL;DR — P0 + P1 LANDED 22 SEP 2026

**Admin UI restored 3/13 → 13/13. Core PG loop (P1) complete. Health 6/10 → 9/10.**

| Layer | Status | Note |
|---|---|---|
| Database schema (13 biz tables) | DONE | Clean, indexed, idempotent keys |
| Models + 16 PHP enums | DONE | Type-safe, good relations/scopes |
| Domain services (6 singletons) | DONE | Best part — locking, proration, settlement, inquiry conversion |
| Observers (6) + cron schedule | DONE | Ledger self-maintains + late fees (06:00) + reminders (10:00) |
| Filament shell + dashboard (5 widgets) | DONE | Occupancy, dues, movements, floor chart, **P&L** |
| Filament Resources (CRUD UI) | DONE 13/13 | 40 admin routes; all CRUD + workflows navigable |
| P1 workflows (convert/KYC/collect/remind/bulk meters/SLA) | DONE 22-Sep | All via services — no inline DB writes in UI |
| Printable invoice + receipt | DONE 22-Sep | `/invoices/{id}/print`, auth-guarded, browser print-to-PDF |
| Late-fee engine | DONE 22-Sep | Visible other_charges line, once per invoice, nightly cron |
| Seeders / Factories / Demo data | DONE | DatabaseSeeder + DemoSeeder + 12 factories; admin@bizstay.local |
| Tests | DONE | BillingCoreTest 6/6 + P1MoneyMaturityTest 6/6 + 2 example = **14/14 green** |
| Roles, tenant portal, gateway, exports | NOT STARTED | P2/P3 — what separates good tool from best product |

**Health score: 9/10** — backend + full admin loop + money maturity basics; gateway/portal/roles remain.

## 1b. WHAT WAS FIXED 22 SEP 2026
- **P0:** 10 Resources created (Guest/Booking/Inquiry/LeaveLog/UtilityMeter/MeterReading/Invoice/Payment/Expense/Complaint). CreateBooking→allocate(), bed-move→changeBed(), meter→record(), guest Aadhaar→hash-only.
- **P0:** `Booking::outstandingDues()` + `Guest::outstandingBalance()` now use balance (total-paid), not total.
- **P0:** Invoice idempotency UNIQUE crash fixed (whereDate lookup); checkout reuses month row as settlement.
- **P1:** `InquiryConversionService` — one-click Convert wizard: guest reuse, KYC verify tick, bed picker (floor/rent hints), deposit policy prefill, first prorated invoice raised on move-in.
- **P1:** GuestResource Verify/Reject KYC row actions; BookingResource notice/withdraw/settle-and-out call the services.
- **P1:** InvoiceResource: Collect (UTR validated for digital methods), Remind, Remind-all-overdue, Apply Late Fees, Print (`/invoices/{id}/print` blade, auth via Filament middleware).
- **P1:** Meters: bulk floor-entry modal (per-meter prev/rate hints, idempotent save, blank rows skipped), `MeterReading::isAnomalous()` icon column (>2× meter average).
- **P1:** Complaints: Start / Assign / Resolve actions, staff column, SLA badge (24h high / 72h rest), SLA-breached + unassigned filters. ExpenseResource: receipt upload/preview + this-month filter.
- **P2:** Late-fee engine: `applyLateFees()` → `other_charges` line, charged once (`late_fee_charged_on` guard), nightly 06:00 cron + admin button. Reminders nightly 10:00 (3-day throttle, structured log).
- **P2:** `ProfitLossWidget` (collected − expenses = net) on dashboard; `Property::monthlyPnL()` reusable.
- Migration `2026_09_22_000001` added: `invoices.late_fee_charged_on/last_reminded_at`, `bookings.assigned_marketer/converted_inquiry_id` (additive, nullable).

## 2. What Is DONE (keep, don't rewrite)

### Module A — Property + Inventory: 90%
Files: `migrations/*000003*`, `Models/Property, Room, Bed`, `Resources/Property, Room, Bed`, `Widgets/Occupancy*`.
Works: singleton building profile with billing rules, sharing 1/2/3/4 auto-creates beds (`101-A/B`), bed state machine, `syncStatusFromBeds()` via observer, bed badge-stack view.
Polish left: room photos, floor-plan upload.

### Module B — Tenant & Stay: 90% backend, 85% UI
Files: `migrations/*000004*`, `Models/Guest, Booking, LeaveLog, Inquiry`, `Services/BedAllocationService` (FOR UPDATE lock, KYC + blacklist guard), `Services/InquiryConversionService` (lead→tenant one-click), `CheckoutSettlementService` (preview-settle-refund).
Works: Aadhaar hash-only, reusable guest, live scope, notice/withdraw/cancel, food opt-out days, convert wizard, KYC verify/reject actions, check-in via allocate(), checkout settlement modal.
Polish left: booking check-in wizard as a true multi-step form, duplicate-Aadhaar warn, doc preview.

### Module C — Utility & Metering: 85% backend, 80% UI
Files: `migrations/*000005*`, `Models/UtilityMeter, MeterReading`, `Services/MeterReadingService` (bulk floor entry, regression guard).
Works: 1 meter/utility/room, multiplier + rate override, `meter+date` unique, share divisor, bulk floor-entry modal with prev/rate hints, anomaly flag (>2× average), invoice-claim link, history view.
Polish left: monthly comparison chart.

### Module D — Finance/Billing: 90% backend, 85% UI
Files: `Models/Invoice` (`BZ-YYYYMM-####`, booking+cycle unique), `Payment` (UTR unique), `Services/InvoiceService` (prorated rent + utility + maintenance - food + late fees), `routes/console.php` (00:30 overdue, 02:00 billing, 06:00 late fees, 10:00 reminders).
Works: full invoice table + edit, Collect with method + UTR (validated for digital), printable invoice/receipt, Remind (single + bulk, 3-day throttle), late-fee engine (visible line item, charged once), Generate-cycle header action, P&L widget.
Broken: no gateway (manual cash/UPI only), no true PDF binary (browser print-to-PDF used instead).

### Module E — Operations: 80% backend, 75% UI
Files: `migrations/*000006*`, `Models/Complaint, Expense`, `Services/InquiryConversionService` (shared).
Works: decoupled FKs, `open` scope, `resolve()` helper, Start/Assign/Resolve actions, staff column, SLA clock (24h high / 72h rest) + breach badge/filters, expense receipts.
Broken: no photo upload on complaints, no kanban layout (list board used).

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
5. ~~Inquiry-to-tenant: one-click Convert (Guest + allocate bed + first prorated invoice), overdue follow-up badge.~~ DONE 22-Sep: `InquiryConversionService` + full convert wizard (guest reuse, KYC verify, bed picker, deposit policy, first prorated invoice).
6. ~~Check-in wizard: KYC verify/reject, bed picker (floor/sharing/AC), deposit auto-calc, first-cycle preview.~~ DONE 22-Sep: BookingResource create (allocate() via service) + GuestResource Verify/Reject KYC row actions.
7. ~~Notice & checkout UI: settlement preview modal + collect/refund buttons.~~ DONE 22-Sep (earlier): notice/withdraw/settle-and-out actions calling CheckoutSettlementService.
8. ~~Money UI: invoice PDF + receipt, Mark Paid (method + UTR), overdue Remind, monthly collection report.~~ DONE 22-Sep: printable invoice (`/invoices/{id}/print`, auth-guarded), Collect (UTR-validated), Remind + Remind-all-overdue, P&L widget.
9. ~~Meters UI: floor bulk-entry grid (`metersOnFloor()` exists), history, claimed-by-invoice link, anomaly flag (>2x avg).~~ DONE 22-Sep: bulk floor-entry modal (per-meter prev/rate hints, idempotent save), anomaly icon column (`MeterReading::isAnomalous()`), invoice link column.
10. ~~Complaints board: kanban open/in_progress/resolved/closed, photo, assign staff, SLA timer.~~ DONE 22-Sep (list board): Start/Assign/Resolve actions, staff column, SLA badge (24h high / 72h rest), SLA-breached + unassigned filters. Photo upload still open.

### P2 — Money maturity + trust (Weeks 4-5)
11. ~~Late-fee engine~~ DONE 22-Sep.
12. Expense approvals + budget vs actual — REMAINS (receipts preview + monthly P&L widget DONE 22-Sep; approvals workflow and budget-vs-actual report still open).
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
| B1 | Guests + KYC | 90% | 85% | doc preview, duplicate-Aadhaar warn |
| B2 | Bookings / stay | 90% | 85% | multi-step wizard, settlement preview modal polish |
| B3 | Inquiries | 80% | 80% | auto follow-up reminders (cron), reminders UI |
| B4 | Leave / food | 70% | 60% | approve flow UI |
| C | Meters/Readings | 85% | 80% | monthly comparison chart |
| D1 | Invoices | 90% | 85% | true PDF binary, GST-ready variant |
| D2 | Payments | 80% | 80% | gateway, refund flow UI |
| D3 | Expenses/P&L | 70% | 80% | approvals, budget vs actual |
| E | Complaints | 80% | 75% | photos, kanban layout |
| X | Roles/Audit | 20% | 20% | Shield, log, 2FA |
| X | Notify/Reports/Portal | 10% | 10% | queue, exports, 2nd panel |

## 6. Suggested Next 5 Commits
1. `fix(admin): restore 10 missing *Resource.php + nav groups` DONE 22-Sep
2. `chore(db): DatabaseSeeder + 13 factories + demo admin` DONE 22-Sep
3. `test(core): proration + allocation lock + invoice idempotency + settlement` DONE 22-Sep
4. `feat(stay): inquiry convert + check-in wizard + checkout actions` DONE 22-Sep (InquiryConversionService, KYC actions, convert wizard)
5. `feat(billing): invoice PDF + Mark Paid + overdue remind + P&L widget` DONE 22-Sep (+ late-fee engine, bulk meter entry, complaint SLA — 14/14 tests green)

## 7. Risks / Decisions
1. Singleton vs multi-property: keep singleton until P3 (schema designed for it).
2. README overclaims (multi-property, 8 resource pages, seed data) — fix or implement; will fail a demo today.
3. ~~`Booking::outstandingDues()` sums `total_due` not balance~~ FIXED 22-Sep: now balance (total - paid).
4. ~~Invoice has Edit but no Create page; add Generate-for-cycle header action.~~ RESOLVED: create stays disabled (correct — system-generated) and the Generate-cycle header action exists on InvoiceResource + DueInvoicesTable.
5. Aadhaar hash-only is correct — never add clear-text column.
6. Late-fee engine charges once per invoice (guard column), not compounding daily — intentional; revisit if per-day fees are wanted.
7. Printable invoice is browser print-to-PDF (no binary PDF lib installed) — swap to dompdf/snappy only if attachments are required (P3).

## 8. HOW MUCH REMAINS (~25%)

Done: backend domain (95%), admin CRUD + P1 workflows (85%), money basics incl. late fees/reminders/print/P&L (85%), tests 14/14 green.

| Block | Effort | What's in it |
|---|---|---|
| P2 leftovers (Wk 4-5) | ~2 wks | expense approvals, budget vs actual, deposit ledger view, Excel exports (rent roll/dues aging/occupancy), payment gateway (Razorpay/UPI + webhook) |
| P3 BEST layer (Wk 6-8) | ~3 wks | roles + audit (spatie/shield, activity log, 2FA), queued notifications (SMS/WhatsApp/email on raise/due/overdue/resolved), tenant portal (2nd panel), public site (availability + inquiry form), multi-property switcher, backups/health/rate-limit |
| Polish backlog | ~1 wk | complaint photos + kanban, booking multi-step wizard, follow-up auto-reminders cron, README truth pass, true PDF binary if needed |

**Total ≈ 6 focused weeks to "best product"; the house is fully runnable in the admin today.**

*If you only do one thing next: wire the payment gateway (P2 #15) — collections are the last manual step in an otherwise self-maintaining money loop. Then roles/audit (P3 #16) before adding staff users.*

