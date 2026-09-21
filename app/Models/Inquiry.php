<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A sales lead (walk-in / portal enquiry). Lives in its own table so the
 * booking ledger only ever contains real, billable stays.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property InquiryStatus $status
 * @property Carbon|null $follow_up_date
 */
class Inquiry extends Model
{
    /** @use HasFactory<\Database\Factories\InquiryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'gender', 'source', 'budget', 'interested_in',
        'preferred_move_in', 'follow_up_date', 'status', 'assigned_to',
        'converted_guest_id', 'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'preferred_move_in' => 'date',
            'follow_up_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'converted_guest_id');
    }

    /**
     * @param  Builder<Inquiry>  $query
     * @return Builder<Inquiry>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InquiryStatus::Converted->value,
            InquiryStatus::Cancelled->value,
        ]);
    }

    public function isOverdueFollowUp(): bool
    {
        return $this->follow_up_date !== null
            && $this->follow_up_date->isPast()
            && $this->status->isOpen();
    }
}
