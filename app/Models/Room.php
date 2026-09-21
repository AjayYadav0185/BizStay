<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'room_number', 'floor', 'sharing_capacity', 'monthly_rent',
        'security_deposit', 'has_ac', 'attached_bathroom', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'has_ac' => 'boolean',
            'attached_bathroom' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function getOccupiedBedsAttribute(): int
    {
        return $this->beds->where('status', 'occupied')->count();
    }
}
