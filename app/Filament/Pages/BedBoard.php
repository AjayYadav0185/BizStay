<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\BedStatus;
use App\Enums\InvoiceStatus;
use App\Models\Room;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The whole house on one screen: every floor, room and bed with who is in
 * it, who is on notice and who owes money — what a warden checks at 9 am.
 */
class BedBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Property';

    protected static ?string $navigationLabel = 'Bed Board';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Bed Board';

    protected static string $view = 'filament.pages.bed-board';

    public string $filter = 'all';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $rooms = Room::query()
            ->with([
                'beds' => fn ($query) => $query->orderBy('position'),
                'beds.currentBooking.guest',
                'beds.currentBooking.invoices' => fn ($query) => $query->whereIn('status', [
                    InvoiceStatus::Unpaid->value,
                    InvoiceStatus::PartiallyPaid->value,
                    InvoiceStatus::Overdue->value,
                ]),
            ])
            ->orderBy('floor_no')
            ->orderBy('room_number')
            ->get();

        /** @var Collection<int, Collection<int, array<string, mixed>>> $floors */
        $floors = $rooms->groupBy('floor_no')->map(fn (Collection $floorRooms) => $floorRooms->map(fn (Room $room): array => [
            'number' => $room->room_number,
            'sharing' => $room->sharing_type->getLabel(),
            'ac' => (bool) $room->has_ac,
            'beds' => $room->beds->map(function ($bed): array {
                $booking = $bed->currentBooking;
                $dues = $booking ? round($booking->invoices->sum(fn ($invoice): float => $invoice->balanceDue()), 2) : 0.0;

                return [
                    'id' => $bed->id,
                    'code' => $bed->bed_code,
                    'status' => $bed->status,
                    'guest' => $booking?->guest?->full_name,
                    'guest_id' => $booking?->guest_id,
                    'notice' => $booking?->isOnNotice() ?? false,
                    'leaving' => $booking?->expected_check_out_date?->format('d M'),
                    'dues' => $dues,
                    'rent' => $booking ? (float) $booking->monthly_rent : $bed->effectiveRent(),
                    'hotel' => $booking?->isHotelStay() ?? false,
                ];
            })->filter(fn (array $bed): bool => $this->matchesFilter($bed))->values()->all(),
        ])->filter(fn (array $room): bool => $room['beds'] !== [])->values());

        $allBeds = $rooms->flatMap->beds;

        return [
            'floors' => $floors->filter(fn (Collection $floorRooms): bool => $floorRooms->isNotEmpty()),
            'counts' => [
                'all' => $allBeds->count(),
                'available' => $allBeds->where('status', BedStatus::Available)->count(),
                'occupied' => $allBeds->where('status', BedStatus::Occupied)->count(),
                'reserved' => $allBeds->where('status', BedStatus::Reserved)->count(),
                'maintenance' => $allBeds->where('status', BedStatus::Maintenance)->count(),
            ],
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'available', 'occupied', 'notice', 'dues', 'maintenance'], true) ? $filter : 'all';
    }

    /**
     * @param  array<string, mixed>  $bed
     */
    private function matchesFilter(array $bed): bool
    {
        return match ($this->filter) {
            'available' => $bed['status'] === BedStatus::Available,
            'occupied' => $bed['status'] === BedStatus::Occupied,
            'maintenance' => $bed['status'] === BedStatus::Maintenance,
            'notice' => $bed['notice'],
            'dues' => $bed['dues'] > 0,
            default => true,
        };
    }
}
