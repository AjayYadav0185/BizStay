<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A guest leave / absence. When food_opt_out is true and the leave is approved,
 * the mess charge for those days is credited back on the next invoice.
 *
 * @property int $id
 * @property int $booking_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $total_days
 * @property bool $food_opt_out
 * @property LeaveStatus $status
 */
class LeaveLog extends Model
{
    /** @use HasFactory<\Database\Factories\LeaveLogFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'booking_id', 'start_date', 'end_date', 'total_days', 'food_opt_out',
        'reason', 'status', 'approved_by', 'approved_at', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
            'food_opt_out' => 'boolean',
            'total_days' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Leave records are inclusive of both start and end day.
     */
    public static function inclusiveDays(Carbon $start, Carbon $end): int
    {
        return (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
    }

    /**
     * @param  Builder<LeaveLog>  $query
     * @return Builder<LeaveLog>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LeaveStatus::Approved->value,
            LeaveStatus::Completed->value,
        ]);
    }

    /**
     * @param  Builder<LeaveLog>  $query
     * @return Builder<LeaveLog>
     */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString());
    }

    /**
     * How many leave days fall inside the given billing window (inclusive).
     */
    public function daysWithin(Carbon $from, Carbon $to): int
    {
        $start = $this->start_date->greaterThan($from) ? $this->start_date->copy() : $from->copy();
        $end = $this->end_date->lessThan($to) ? $this->end_date->copy() : $to->copy();

        if ($end->lessThan($start)) {
            return 0;
        }

        return self::inclusiveDays($start, $end);
    }

    public function qualifiesForFoodDeduction(): bool
    {
        return $this->food_opt_out && $this->status->qualifiesForFoodDeduction();
    }

    public function approve(?int $userId = null): bool
    {
        return $this->forceFill([
            'status' => LeaveStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();
    }

    public function reject(?int $userId = null, ?string $notes = null): bool
    {
        return $this->forceFill([
            'status' => LeaveStatus::Rejected,
            'approved_by' => $userId,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
        ])->save();
    }
}
