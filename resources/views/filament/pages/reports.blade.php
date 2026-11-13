@php use App\Support\IndianCurrency as Rs; @endphp
<x-filament-panels::page>
    <style>
        .bz-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        .bz-grid-sm { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); }
        .bz-tile { border: 1px solid rgb(var(--gray-200)); border-radius: .75rem; padding: .875rem 1rem; }
        .dark .bz-tile { border-color: rgb(255 255 255 / .1); }
        .bz-label { font-size: .75rem; color: rgb(var(--gray-500)); }
        .bz-value { font-size: 1.375rem; font-weight: 700; margin-top: .25rem; }
        .bz-ok { color: rgb(var(--success-600)); }
        .bz-warn { color: rgb(var(--warning-600)); }
        .bz-bad { color: rgb(var(--danger-600)); }
        .bz-primary { color: rgb(var(--primary-600)); }
        .bz-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem; margin-bottom: 1.25rem; }
        .bz-report { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .bz-report p + p { margin-top: .25rem; }
        .bz-muted { font-size: .875rem; color: rgb(var(--gray-500)); }
        .bz-tiny { font-size: .75rem; color: rgb(var(--gray-400)); }
    </style>

    <div class="bz-grid">
        <div class="bz-tile">
            <p class="bz-label">Collected this month</p>
            <p class="bz-value bz-ok">{{ Rs::format($pnl['collected'], 0) }}</p>
        </div>
        <div class="bz-tile">
            <p class="bz-label">Expenses this month</p>
            <p class="bz-value">{{ Rs::format($pnl['expenses'], 0) }}</p>
        </div>
        <div class="bz-tile">
            <p class="bz-label">Cash profit this month</p>
            <p class="bz-value {{ $pnl['profit'] >= 0 ? 'bz-ok' : 'bz-bad' }}">{{ Rs::format($pnl['profit'], 0) }}</p>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Dues aging</x-slot>
        <x-slot name="description">Outstanding balance by how late it is. Anything past 60 days needs a call today.</x-slot>
        <div class="bz-grid-sm">
            @foreach ([
                'current' => ['Not yet due', ''],
                'd30' => ['1–30 days', 'bz-warn'],
                'd60' => ['31–60 days', 'bz-warn'],
                'd90' => ['61–90 days', 'bz-bad'],
                'd90plus' => ['90+ days', 'bz-bad'],
                'total' => ['Total', 'bz-primary'],
            ] as $key => [$label, $tone])
                <div class="bz-tile">
                    <p class="bz-label">{{ $label }}</p>
                    <p class="bz-value {{ $tone }}" style="font-size:1.125rem">{{ Rs::format($aging[$key], 0) }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Downloads — CSV (opens in Excel / Google Sheets)</x-slot>
        <x-slot name="description">The period applies to dated reports.</x-slot>

        <div class="bz-row">
            <label>
                <span class="bz-muted">From</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </label>
            <label>
                <span class="bz-muted">To</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </label>
            <x-filament::button size="sm" color="gray" wire:click="setRange('month')">This month</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="setRange('last-month')">Last month</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="setRange('quarter')">This quarter</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="setRange('fy')">This FY</x-filament::button>
        </div>

        <div class="bz-grid">
            @foreach ($catalogue as $key => $report)
                <div class="bz-tile bz-report">
                    <div>
                        <p style="font-weight:600">{{ $report['label'] }}</p>
                        <p class="bz-muted">{{ $report['description'] }}</p>
                        <p class="bz-tiny">{{ $report['dated'] ? \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($toDate)->format('d M Y') : 'As of today' }}</p>
                    </div>
                    <x-filament::button
                        tag="a"
                        size="sm"
                        icon="heroicon-o-arrow-down-tray"
                        :href="route('reports.download', ['report' => $key] + ($report['dated'] ? ['from' => $fromDate, 'to' => $toDate] : []))"
                    >
                        CSV
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
