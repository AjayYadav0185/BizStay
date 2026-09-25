<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manager dashboard numbers for the Flutter home screen.
 * Mirrors the Filament widgets (occupancy, dues, movements, P&L)
 * but as one cheap JSON payload.
 */
final class DashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $property = Property::current();

        $totalBeds = \App\Models\Bed::query()->count();
        $occupiedBeds = \App\Models\Bed::query()->where('status', 'occupied')->count();
        $freeBeds = \App\Models\Bed::query()->where('status', 'available')->count();

        $due = (float) Invoice::query()
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->sum('total_due');
        $paid = (float) Invoice::query()
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->sum('amount_paid');

        $overdue = Invoice::query()->overdue()->count();
        $openComplaints = \App\Models\Complaint::query()->open()->count();
        $openInquiries = \App\Models\Inquiry::query()->open()->count();
        $liveStays = Booking::query()->live()->count();

        $pnl = $property?->monthlyPnL(now()) ?? ['collected' => 0, 'expenses' => 0, 'profit' => 0];

        return response()->json([
            'property' => $property ? [
                'name' => $property->name,
                'locality' => $property->locality,
                'city' => $property->city,
                'occupancy_percent' => $property->occupancyPercent(),
            ] : null,
            'beds' => ['total' => $totalBeds, 'occupied' => $occupiedBeds, 'free' => $freeBeds],
            'stays' => ['live' => $liveStays],
            'dues' => ['outstanding' => round($due - $paid, 2), 'overdue_invoices' => $overdue],
            'ops' => ['open_complaints' => $openComplaints, 'open_inquiries' => $openInquiries],
            'pnl' => $pnl,
        ]);
    }
}
