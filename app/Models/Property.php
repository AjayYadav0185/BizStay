<?php

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'address', 'locality', 'city', 'state', 'pincode',
        'manager_name', 'contact_phone', 'total_floors', 'amenities',
        'security_deposit_months', 'notice_period_days', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function beds()
    {
        return $this->hasManyThrough(Bed::class, Room::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeTenants(): HasMany
    {
        return $this->hasMany(Tenant::class)->whereIn('status', ['active', 'notice_period']);
    }

    public function getFullAddressAttribute(): string
    {
        return trim($this->address.', '.$this->locality.', '.$this->city.', '.$this->state.' '.$this->pincode, ' ,');
    }

    public function getOccupancyPercentAttribute(): float
    {
        $total = $this->beds()->count();

        if ($total === 0) {
            return 0.0;
        }

        $occupied = $this->beds()->where('status', 'occupied')->count();

        return round($occupied / $total * 100, 1);
    }
}
