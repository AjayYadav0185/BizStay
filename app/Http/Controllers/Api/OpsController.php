<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InquiryStatus;
use App\Models\Complaint;
use App\Models\Inquiry;
use App\Models\Visitor;
use App\Support\Ncr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OpsController
{
    public function complaints(Request $request): JsonResponse
    {
        $query = Complaint::query()->with(['guest', 'room']);

        if ($request->user()->isTenant()) {
            $query->where('guest_id', $request->user()->guest_id);
        }

        $rows = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($rows->through(fn (Complaint $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'category' => $c->category,
            'priority' => $c->priority->value,
            'status' => $c->status->value,
            'guest' => $c->guest?->full_name,
            'room' => $c->room?->room_number,
            'sla_breached' => $c->slaBreached(),
            'created' => $c->created_at?->toDateString(),
        ]));
    }

    public function raiseComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'guest_id' => ['nullable', 'exists:guests,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);

        $user = $request->user();

        // A tenant can only ever raise a ticket for themselves and their own room.
        if ($user->isTenant()) {
            $data['guest_id'] = $user->guest_id;
            $data['room_id'] = $user->guest?->currentBooking?->bed?->room_id;
        }

        $complaint = Complaint::query()->create([
            'guest_id' => $data['guest_id'] ?? $user->guest_id,
            'room_id' => $data['room_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        return response()->json(['id' => $complaint->id], 201);
    }

    public function visitors(Request $request): JsonResponse
    {
        $rows = Visitor::query()
            ->with('guest.currentBooking.bed')
            ->when($request->boolean('inside'), fn ($q) => $q->inside())
            ->orderByDesc('checked_in_at')
            ->paginate(30);

        return response()->json($rows->through(fn (Visitor $v) => [
            'id' => $v->id,
            'visitor' => $v->visitor_name,
            'phone' => $v->visitor_phone,
            'relation' => $v->relation,
            'meeting' => $v->guest?->full_name,
            'bed' => $v->guest?->currentBooking?->bed?->bed_code,
            'in' => $v->checked_in_at?->toIso8601String(),
            'out' => $v->checked_out_at?->toIso8601String(),
            'overnight' => $v->is_overnight,
        ]));
    }

    public function storeVisitor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitor_name' => ['required', 'string', 'max:255'],
            'visitor_phone' => ['nullable', 'string', 'max:20'],
            'guest_id' => ['nullable', 'exists:guests,id'],
            'relation' => ['nullable', 'string', 'max:40'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'id_proof' => ['nullable', 'string', 'max:60'],
            'is_overnight' => ['nullable', 'boolean'],
        ]);

        $visitor = Visitor::query()->create($data + [
            'checked_in_at' => now(),
            'is_overnight' => (bool) ($data['is_overnight'] ?? false),
            'logged_by' => $request->user()->id,
        ]);

        return response()->json(['id' => $visitor->id], 201);
    }

    public function checkoutVisitor(Visitor $visitor): JsonResponse
    {
        $visitor->checkOut();

        return response()->json(['id' => $visitor->id, 'out' => $visitor->checked_out_at?->toIso8601String()]);
    }

    public function storeInquiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'source' => ['nullable', 'in:'.implode(',', array_keys(Ncr::inquirySources()))],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'interested_in' => ['nullable', 'string', 'max:120'],
            'preferred_move_in' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $inquiry = Inquiry::query()->create($data + [
            'gender' => $data['gender'] ?? 'male',
            'source' => $data['source'] ?? 'walk_in',
            'status' => InquiryStatus::New,
            'follow_up_date' => $data['follow_up_date'] ?? now()->addDay()->toDateString(),
            'assigned_to' => $request->user()->id,
        ]);

        return response()->json(['id' => $inquiry->id], 201);
    }

    public function inquiries(Request $request): JsonResponse
    {
        $rows = Inquiry::query()
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('follow_up_date')
            ->paginate(30);

        return response()->json($rows->through(fn (Inquiry $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'phone' => $i->phone,
            'source' => $i->source,
            'budget' => (float) ($i->budget ?? 0),
            'status' => $i->status->value,
            'follow_up' => $i->follow_up_date?->toDateString(),
            'overdue' => $i->isOverdueFollowUp(),
        ]));
    }
}
