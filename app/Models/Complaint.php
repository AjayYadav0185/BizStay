<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A maintenance/operations request. Optionally raised by a guest and/or for a
 * specific room.
 *
 * @property int $id
 * @property string $title
 * @property ComplaintStatus $status
 * @property ComplaintPriority $priority
 */
class Complaint extends Model
{
    /** @use HasFactory<\Database\Factories\ComplaintFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'guest_id', 'room_id', 'title', 'description', 'category', 'priority',
        'status', 'assigned_to', 'resolved_at', 'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'priority' => ComplaintPriority::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @param  Builder<Complaint>  $query
     * @return Builder<Complaint>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ComplaintStatus::Open->value,
            ComplaintStatus::InProgress->value,
        ]);
    }

    public function resolve(?string $notes = null): bool
    {
        return $this->forceFill([
            'status' => ComplaintStatus::Resolved,
            'resolved_at' => now(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ])->save();
    }
}
