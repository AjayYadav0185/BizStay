{{--
    Visual bed stack for the room grid.
    Renders one chip per bed, coloured by its live status, so a manager can read
    a whole floor's availability without opening a single room.

    @var \App\Models\Room $getRecord()
--}}
@php
    /** @var \App\Models\Room $room */
    $room = $getRecord();
    $beds = $room->beds->sortBy('position');
@endphp

@if ($beds->isEmpty())
    <span class="text-xs text-gray-400 dark:text-gray-500 italic">No beds — set sharing</span>
@else
    <div class="flex flex-wrap items-center gap-1">
        @foreach ($beds as $bed)
            <span
                title="{{ $bed->bed_code }} · {{ $bed->status->getLabel() }}{{ $bed->currentBooking?->guest ? ' · '.$bed->currentBooking->guest->full_name : '' }}"
                style="background-color: {{ $bed->status->hex() }}1a; border-color: {{ $bed->status->hex() }}; color: {{ $bed->status->hex() }};"
                class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-semibold leading-4"
            >
                <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $bed->status->hex() }};"></span>
                {{ $bed->bed_code }}
            </span>
        @endforeach
    </div>

    <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
        <span class="font-semibold">{{ $room->occupiedBedCount() }}/{{ $beds->count() }} occupied</span>
        <span aria-hidden="true">·</span>
        <span>{{ $room->availableBedCount() }} free</span>
        @if ($room->maintenanceBedCount() > 0)
            <span aria-hidden="true">·</span>
            <span class="text-danger-600 dark:text-danger-400">{{ $room->maintenanceBedCount() }} maintenance</span>
        @endif
    </div>
@endif
