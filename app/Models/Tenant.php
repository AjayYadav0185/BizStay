<?php

namespace App\Models;

use App\Enums\BedStatus;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'bed_id', 'full_name', 'phone', 'email', 'gender',
        'id_proof_type', 'id_proof_number', 'id_proof_file', 'kyc_verified',
        'permanent_address', 'home_city', 'occupation', 'company_name',
        'emergency_contact_name', 'emergency_contact_phone', 'monthly_rent',
        'security_deposit', 'joining_date', 'rent_due_day', 'notice_date',
        'vacated_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'joining_date' => 'date',
            'notice_date' => 'date',
            'vacated_date' => 'date',
            'kyc_verified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep bed occupancy in sync with tenant lifecycle.
        static::saved(function (Tenant $tenant) {
            if (! $tenant->bed_id) {
                return;
            }

            $bed = Bed::find($tenant->bed_id);

            if (! $bed) {
                return;
            }

            $isActive = in_array($tenant->status?->value ?? $tenant->getOriginal('status'), ['active', 'notice_period']);
            $bed->update(['status' => $isActive ? BedStatus::Occupied : BedStatus::Vacant]);
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function getPendingDueAttribute(): float
    {
        return (float) $this->payments()
            ->where('type', 'rent')
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');
    }

    public function getLabel(): string
    {
        return $this->full_name.' ('.$this->phone.')';
    }
}
