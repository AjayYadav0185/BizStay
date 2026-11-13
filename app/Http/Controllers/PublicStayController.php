<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BedStatus;
use App\Enums\InquiryStatus;
use App\Models\Bed;
use App\Models\Inquiry;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The building's public page: live bed availability by room type, amenities,
 * location and an enquiry form that lands straight in the Inquiries pipeline
 * (source = website) for the manager to call back.
 */
final class PublicStayController extends Controller
{
    public function show(): View
    {
        $property = Property::current();

        abort_if($property === null || ! $property->public_listing, 404);

        $roomTypes = Bed::query()
            ->with('room')
            ->get()
            ->filter(fn (Bed $bed): bool => $bed->room !== null)
            ->groupBy(fn (Bed $bed): string => $bed->room->sharing_type->value.'|'.($bed->room->has_ac ? 'ac' : 'non-ac'))
            ->map(function ($beds) {
                /** @var Bed $first */
                $first = $beds->first();
                $rents = $beds->map(fn (Bed $bed): float => $bed->effectiveRent());

                return [
                    'sharing' => $first->room->sharing_type->getLabel(),
                    'ac' => (bool) $first->room->has_ac,
                    'from' => (float) $rents->min(),
                    'nightly' => (float) $beds->map(fn (Bed $bed): float => (float) ($bed->room->nightly_rate ?? 0))->filter()->min(),
                    'available' => $beds->filter(fn (Bed $bed): bool => $bed->status === BedStatus::Available)->count(),
                ];
            })
            ->sortBy('from')
            ->values();

        return view('public.stay', [
            'property' => $property,
            'roomTypes' => $roomTypes,
        ]);
    }

    public function enquire(Request $request): RedirectResponse
    {
        $property = Property::current();
        abort_if($property === null || ! $property->public_listing, 404);

        // Honeypot: real people never see or fill this field.
        if (filled($request->input('website'))) {
            return redirect()->route('stay.show')->with('enquiry_sent', true);
        }

        // People type numbers with spaces / dashes: "+91 98111-22233".
        $request->merge(['phone' => preg_replace('/[\s\-()]/', '', (string) $request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'interested_in' => ['nullable', 'string', 'max:120'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'preferred_move_in' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
        ]);

        $phone = substr(preg_replace('/\D/', '', $data['phone']) ?? '', -10);

        // One open lead per number: a repeat enquiry refreshes it instead of duplicating.
        $inquiry = Inquiry::query()->open()->where('phone', $phone)->first() ?? new Inquiry([
            'phone' => $phone,
            'source' => 'website',
            'status' => InquiryStatus::New,
        ]);

        $inquiry->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? $inquiry->email,
            'gender' => $data['gender'],
            'interested_in' => $data['interested_in'] ?? $inquiry->interested_in,
            'budget' => $data['budget'] ?? $inquiry->budget,
            'preferred_move_in' => $data['preferred_move_in'] ?? $inquiry->preferred_move_in,
            'follow_up_date' => now()->toDateString(),
            'message' => trim(($inquiry->message ? $inquiry->message."\n" : '').($data['message'] ?? '')) ?: null,
        ])->save();

        return redirect()->route('stay.show')->with('enquiry_sent', true);
    }
}
