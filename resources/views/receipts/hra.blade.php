@php
    use App\Support\IndianCurrency as Rs;
    use App\Services\RentReceiptService;
    $landlord = $property?->owner_name ?: ($property?->name ?? 'PG Owner');
    $premises = $guest->currentBooking?->bed ? 'Bed '.$guest->currentBooking->bed->bed_code.', ' : '';
    $premises .= $property?->full_address;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rent Receipts FY {{ $data['fy'] }} — {{ $guest->full_name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1f2937; padding: 32px; max-width: 820px; margin: 0 auto; background: #f8fafc; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 24px; }
    .toolbar form { display: flex; gap: 8px; align-items: center; }
    select, button { font: inherit; padding: 8px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; }
    button.primary { background: #e11d48; border-color: #e11d48; color: #fff; cursor: pointer; }
    .summary { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; font-size: 14px; }
    .warn { color: #b45309; margin-top: 6px; }
    .receipt { background: #fff; border: 1.5px dashed #94a3b8; border-radius: 10px; padding: 22px 26px; margin-bottom: 18px; page-break-inside: avoid; }
    .receipt h2 { font-size: 15px; letter-spacing: 2px; text-align: center; margin-bottom: 14px; }
    .row { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-bottom: 12px; }
    .body { font-size: 14px; line-height: 1.8; }
    .amount { font-weight: 700; }
    .sign { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 22px; font-size: 12px; color: #475569; }
    .stamp { border: 1px solid #cbd5e1; width: 120px; height: 64px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 11px; }
    .empty { text-align: center; padding: 48px; color: #64748b; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
    @media print { body { background: #fff; padding: 0; } .toolbar, .summary { display: none; } .receipt { border-color: #475569; } }
</style>
</head>
<body>
<div class="toolbar">
    <form method="get">
        <label for="fy">Financial year</label>
        <select id="fy" name="fy" onchange="this.form.submit()">
            @foreach (range(RentReceiptService::financialYearOf(), RentReceiptService::financialYearOf() - 3) as $year)
                <option value="{{ $year }}" @selected($year === $fyStart)>FY {{ RentReceiptService::financialYearLabel($year) }}</option>
            @endforeach
        </select>
    </form>
    <button class="primary" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="summary">
    <strong>{{ $guest->full_name }}</strong> · {{ count($data['receipts']) }} receipt(s) · Total rent paid in FY {{ $data['fy'] }}: <strong>{{ Rs::format($data['total']) }}</strong>
    @if ($data['needs_landlord_pan'] && blank($property?->owner_pan))
        <div class="warn">Annual rent is above ₹1,00,000 — the employer will ask for the landlord's PAN. Add it under Building Profile → Owner PAN.</div>
    @endif
</div>

@forelse ($data['receipts'] as $receipt)
    <div class="receipt">
        <h2>RENT RECEIPT</h2>
        <div class="row">
            <span>Receipt for {{ $receipt['month'] }} · Ref {{ $receipt['invoice'] }}</span>
            <span>Date: {{ $receipt['paid_on'] ?? '—' }}</span>
        </div>
        <div class="body">
            Received with thanks from <strong>{{ $guest->full_name }}</strong> the sum of
            <span class="amount">{{ Rs::format($receipt['amount']) }}</span>
            ({{ Rs::inWords($receipt['amount']) }}) towards rent for the period
            <strong>{{ $receipt['period'] }}</strong> for the premises at {{ $premises }}.
        </div>
        <div class="sign">
            <div>
                Landlord: <strong>{{ $landlord }}</strong><br>
                @if ($property?->owner_pan)PAN: {{ strtoupper($property->owner_pan) }}<br>@endif
                {{ $property?->name }}
            </div>
            <div class="stamp">Signature / Stamp</div>
        </div>
    </div>
@empty
    <div class="empty">No rent payments recorded for FY {{ $data['fy'] }} yet.</div>
@endforelse
</body>
</html>
