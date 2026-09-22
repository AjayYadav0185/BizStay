<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1f2937; padding: 40px; max-width: 820px; margin: 0 auto; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #f59e0b; padding-bottom: 16px; margin-bottom: 24px; }
    h1 { font-size: 22px; letter-spacing: 1px; }
    .brand { font-size: 20px; font-weight: 700; }
    .muted { color: #6b7280; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 14px; }
    th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { border: none; padding: 6px 12px; }
    .totals .grand { font-weight: 700; font-size: 16px; border-top: 2px solid #1f2937; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .unpaid { background: #fef3c7; color: #92400e; }
    .overdue { background: #fee2e2; color: #991b1b; }
    .partially_paid { background: #dbeafe; color: #1e40af; }
    .paid { background: #dcfce7; color: #166534; }
    .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 20px; }
    .cols { display: flex; gap: 32px; margin-top: 20px; }
    .cols > div { flex: 1; }
    .print-btn { position: fixed; top: 16px; right: 16px; padding: 10px 20px; background: #f59e0b; color: #fff; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; }
    @media print { .print-btn { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Print / Save PDF</button>

<div class="head">
    <div>
        <div class="brand">{{ $property?->name ?? 'BizStay PG' }}</div>
        <div class="muted">{{ $property?->full_address }}</div>
        <div class="muted">{{ $property?->contact_phone }} · {{ $property?->contact_email }}</div>
    </div>
    <div style="text-align:right">
        <h1>TAX INVOICE</h1>
        <div class="muted">{{ $invoice->invoice_number }}</div>
        <div class="muted">Cycle: {{ $invoice->billing_cycle_start->format('d M Y') }} – {{ $invoice->billing_cycle_end->format('d M Y') }}</div>
        <div class="muted">Due: {{ $invoice->due_date->format('d M Y') }}</div>
        <span class="badge {{ $invoice->status->value }}">{{ $invoice->status->getLabel() }}</span>
    </div>
</div>

<div class="cols">
    <div>
        <strong>Billed to</strong><br>
        {{ $invoice->booking?->guest?->full_name ?? '—' }}<br>
        <span class="muted">{{ $invoice->booking?->guest?->phone }}</span><br>
        <span class="muted">Bed {{ $invoice->booking?->bed?->bed_code ?? '—' }} · Room {{ $invoice->booking?->bed?->room?->room_number ?? '—' }}</span>
    </div>
    <div style="text-align:right">
        <strong>Stay</strong><br>
        <span class="muted">Check-in {{ $invoice->booking?->check_in_date?->format('d M Y') ?? '—' }}</span>
    </div>
</div>
<table>
    <thead>
        <tr><th>Description</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
        <tr><td>Rent ({{ $invoice->billing_cycle_start->format('M Y') }})</td><td class="num">₹{{ number_format((float) $invoice->rent_amount, 2) }}</td></tr>
        @if ((float) $invoice->utility_amount > 0)
        <tr><td>Utility charges (metered)</td><td class="num">₹{{ number_format((float) $invoice->utility_amount, 2) }}</td></tr>
        @endif
        @if ((float) $invoice->maintenance_charges > 0)
        <tr><td>Maintenance</td><td class="num">₹{{ number_format((float) $invoice->maintenance_charges, 2) }}</td></tr>
        @endif
        @if ((float) $invoice->other_charges != 0.0)
        <tr><td>Other charges</td><td class="num">₹{{ number_format((float) $invoice->other_charges, 2) }}</td></tr>
        @endif
        @if ($invoice->is_checkout_settlement)
        <tr><td class="muted">Check-out settlement invoice</td><td></td></tr>
        @endif
        @if ((float) $invoice->food_deduction > 0)
        <tr><td>Mess credit (leave / opt-out days)</td><td class="num">−₹{{ number_format((float) $invoice->food_deduction, 2) }}</td></tr>
        @endif
        @if ($invoice->late_fee_charged_on !== null)
        <tr><td>Late fee (charged {{ $invoice->late_fee_charged_on->format('d M Y') }})</td><td class="num">included in other charges</td></tr>
        @endif
    </tbody>
</table>

<table class="totals">
    <tr><td class="num" style="width:70%"></td><td class="num muted">Total due</td><td class="num">₹{{ number_format((float) $invoice->total_due, 2) }}</td></tr>
    <tr><td></td><td class="num muted">Paid</td><td class="num">₹{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
    <tr class="grand"><td></td><td class="num muted">Balance</td><td class="num">₹{{ number_format($balance, 2) }}</td></tr>
</table>

@if ($payments->count())
<div class="box">
    <strong>Payments received</strong>
    <table>
        <thead><tr><th>Date</th><th>Method</th><th>Type</th><th>Reference</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($payments as $payment)
            <tr>
                <td>{{ $payment->paid_on?->format('d M Y') }}</td>
                <td>{{ $payment->payment_method->getLabel() }}</td>
                <td>{{ ucfirst($payment->payment_type->value) }}</td>
                <td class="muted">{{ $payment->transaction_id ?? '—' }}</td>
                <td class="num">₹{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if ($invoice->notes)
<div class="box muted">{{ $invoice->notes }}</div>
@endif

<p class="muted" style="margin-top:32px;text-align:center">This is a computer-generated invoice from {{ $property?->name ?? 'BizStay PG' }} and does not require a signature.</p>
</body>
</html>

