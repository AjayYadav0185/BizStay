<?php

namespace App\Models;

use App\Enums\BedStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    /** @use HasFactory<\Database\Factories\BedFactory> */
    use HasFactory;

    protected $fillable = ['room_id', 'bed_number', 'status', 'monthly_rent', 'notes'];

    protected function casts(): array
    {
        return ['status' => BedStatus::class];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function property()
    {
        return $this->hasOneThrough(Property::class, Room::class);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class)->whereIn('status', ['active', 'notice_period']);
    }

    public function getEffectiveRentAttribute(): float
    {
        return (float) ($this->monthly_rent ?? $this->room->monthly_rent);
    }
}
