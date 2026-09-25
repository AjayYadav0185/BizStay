<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StayController
{
    public function rooms(): JsonResponse
    {
        $rooms = \App\Models\Room::query()
            ->with(['beds.currentBooking.guest'])
            ->orderBy('room_number')
            ->paginate(50);

        return response()->json($rooms->through(fn ($room) => [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'floor_no' => $room->floor_no,
            'sharing_type' => $room->sharing_type->value,
            'base_rent_per_bed' => (float) $room->base_rent_per_bed,
            'nightly_rate' => (float) ($room->nightly_rate ?? 0),
            'status' => $room->status->value,
            'has_ac' => (bool) $room->has_ac,
            'occupancy_percent' => $room->occupancyPercent(),
            'beds' => $room->beds->map(fn ($bed) => [
                'id' => $bed->id,
                'bed_code' => $bed->bed_code,
                'status' => $bed->status->value,
                'guest' => $bed->currentBooking?->guest?->full_name,
            ])->values(),
        ]));
    }

    public function guests(Request $request): JsonResponse
    {
        $guests = Guest::query()
            ->with(['currentBooking.bed.room'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->orderBy('full_name')
            ->paginate(30);

        return response()->json($guests->through(fn (Guest $g) => [
            'id' => $g->id,
            'full_name' => $g->full_name,
            'phone' => $g->phone,
            'kyc_status' => $g->kyc_status->value,
            'aadhaar' => $g->masked_aadhaar,
            'bed' => $g->currentBooking?->bed?->bed_code,
            'room' => $g->currentBooking?->bed?->room?->room_number,
            'outstanding' => $g->outstandingBalance(),
        ]));
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['guest', 'bed.room'])
            ->when($request->boolean('live'), fn ($q) => $q->live())
            ->orderByDesc('check_in_date')
            ->paginate(30);

        return response()->json($bookings->through(fn (Booking $b) => [
            'id' => $b->id,
            'guest' => $b->guest?->full_name,
            'phone' => $b->guest?->phone,
            'bed' => $b->bed?->bed_code,
            'room' => $b->bed?->room?->room_number,
            'stay_type' => $b->stay_type ?? 'pg',
            'status' => $b->status->value,
            'check_in' => $b->check_in_date?->toDateString(),
            'monthly_rent' => (float) $b->monthly_rent,
            'outstanding' => $b->outstandingDues(),
        ]));
    }
}
