<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'name', 'phone', 'email', 'gender', 'source', 'budget',
        'interested_in', 'preferred_move_in', 'follow_up_date', 'status',
        'assigned_to', 'message',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'preferred_move_in' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
