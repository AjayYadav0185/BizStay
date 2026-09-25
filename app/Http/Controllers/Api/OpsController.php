<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Complaint;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OpsController
{
    public function complaints(Request $request): JsonResponse
    {
        $query = Complaint::query()->with(['guest', 'room']);

        if ($request->user()->isTenant() && $request->user()->guest_id) {
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

        $complaint = Complaint::query()->create([
            'guest_id' => $data['guest_id'] ?? $request->user()->guest_id,
            'room_id' => $data['room_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        return response()->json(['id' => $complaint->id], 201);
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
