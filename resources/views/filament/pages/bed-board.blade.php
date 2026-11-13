@php
    use App\Enums\BedStatus;
    use App\Support\IndianCurrency as Rs;
    use App\Filament\Resources\GuestResource;
    use App\Filament\Resources\BedResource;
@endphp
<x-filament-panels::page>
    <style>
        .bb-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
        .bb-floor + .bb-floor { margin-top: 1.5rem; }
        .bb-floor h3 { font-weight: 700; font-size: .875rem; letter-spacing: .04em; text-transform: uppercase; color: rgb(var(--gray-500)); margin-bottom: .75rem; }
        .bb-rooms { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
        .bb-room { border: 1px solid rgb(var(--gray-200)); border-radius: .875rem; padding: .875rem; background: #fff; }
        .dark .bb-room { background: rgb(var(--gray-900)); border-color: rgb(255 255 255 / .1); }
        .bb-room-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .625rem; }
        .bb-room-head strong { font-size: 1rem; }
        .bb-room-head span { font-size: .75rem; color: rgb(var(--gray-500)); }
        .bb-beds { display: grid; gap: .5rem; }
        .bb-bed { display: block; border-radius: .625rem; padding: .5rem .625rem; border-left: 4px solid; text-decoration: none; transition: transform .1s; }
        .bb-bed:hover { transform: translateX(2px); }
        .bb-bed .top { display: flex; justify-content: space-between; gap: .5rem; font-size: .75rem; font-weight: 700; }
        .bb-bed .name { font-size: .875rem; margin-top: .125rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bb-bed .meta { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .25rem; font-size: .6875rem; }
        .bb-chip { border-radius: 999px; padding: 0 .4rem; font-weight: 600; }
        .s-available { background: rgb(var(--success-50)); border-color: rgb(var(--success-500)); color: rgb(var(--success-800)); }
        .s-occupied { background: rgb(var(--primary-50)); border-color: rgb(var(--primary-500)); color: rgb(var(--gray-800)); }
        .s-reserved { background: rgb(var(--warning-50)); border-color: rgb(var(--warning-500)); color: rgb(var(--warning-800)); }
        .s-maintenance { background: rgb(var(--gray-100)); border-color: rgb(var(--gray-400)); color: rgb(var(--gray-600)); }
        .dark .s-available, .dark .s-occupied, .dark .s-reserved, .dark .s-maintenance { background: rgb(255 255 255 / .04); color: rgb(var(--gray-200)); }
        .c-dues { background: rgb(var(--danger-100)); color: rgb(var(--danger-700)); }
        .c-notice { background: rgb(var(--warning-100)); color: rgb(var(--warning-800)); }
        .c-hotel { background: rgb(var(--info-100)); color: rgb(var(--gray-700)); }
        .bb-empty { text-align: center; padding: 3rem 1rem; color: rgb(var(--gray-500)); }
    </style>

    <div class="bb-filters">
        @foreach ([
            'all' => 'All beds ('.$counts['all'].')',
            'available' => 'Vacant ('.$counts['available'].')',
            'occupied' => 'Occupied ('.$counts['occupied'].')',
            'notice' => 'On notice',
            'dues' => 'With dues',
            'maintenance' => 'Maintenance ('.$counts['maintenance'].')',
        ] as $key => $label)
            <x-filament::button size="sm" :color="$filter === $key ? 'primary' : 'gray'" wire:click="setFilter('{{ $key }}')">{{ $label }}</x-filament::button>
        @endforeach
    </div>

    @forelse ($floors as $floorNo => $rooms)
        <section class="bb-floor">
            <h3>{{ (int) $floorNo === 0 ? 'Ground floor' : 'Floor '.$floorNo }}</h3>
            <div class="bb-rooms">
                @foreach ($rooms as $room)
                    <div class="bb-room">
                        <div class="bb-room-head">
                            <strong>Room {{ $room['number'] }}</strong>
                            <span>{{ $room['sharing'] }} · {{ $room['ac'] ? 'AC' : 'Non-AC' }}</span>
                        </div>
                        <div class="bb-beds">
                            @foreach ($room['beds'] as $bed)
                                <a class="bb-bed s-{{ $bed['status']->value }}"
                                   href="{{ $bed['guest_id'] ? GuestResource::getUrl('edit', ['record' => $bed['guest_id']]) : BedResource::getUrl('edit', ['record' => $bed['id']]) }}">
                                    <div class="top">
                                        <span>{{ $bed['code'] }}</span>
                                        <span>{{ $bed['status']->getLabel() }}</span>
                                    </div>
                                    <div class="name">{{ $bed['guest'] ?? Rs::format($bed['rent'], 0).' / month' }}</div>
                                    @if ($bed['dues'] > 0 || $bed['notice'] || $bed['hotel'])
                                        <div class="meta">
                                            @if ($bed['dues'] > 0)<span class="bb-chip c-dues">Due {{ Rs::format($bed['dues'], 0) }}</span>@endif
                                            @if ($bed['notice'])<span class="bb-chip c-notice">Leaving {{ $bed['leaving'] ?? 'soon' }}</span>@endif
                                            @if ($bed['hotel'])<span class="bb-chip c-hotel">Hotel</span>@endif
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bb-empty">No beds match this filter.</div>
    @endforelse
</x-filament-panels::page>
