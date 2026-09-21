<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person who may stay with us. Guests are reusable: the same row carries a
 * guest across multiple stays (bookings), so history and KYC survive checkout.
 *
 * Aadhaar is stored only as a SHA-256 digest (duplicate detection) plus the last
 * four digits (display) — never in clear text.
 *
 * @property int $id
 * @property string $full_name
 * @property string $phone
 * @property KycStatus $kyc_status
 * @property array<int, string>|null $kyc_documents
 */
class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'full_name', 'phone', 'alt_phone', 'email',
        'adhaar_number_hash', 'adhaar_last4', 'id_proof_type', 'id_proof_number',
        'kyc_status', 'kyc_documents', 'kyc_verified_at', 'kyc_remarks',
        'gender', 'date_of_birth', 'permanent_address', 'home_city',
        'occupation', 'company_name',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'is_blacklisted', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kyc_status' => KycStatus::class,
            'kyc_documents' => 'array',
            'kyc_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function currentBooking(): HasOne
    {
        return $this->hasOne(Booking::class)
            ->whereIn('status', [
                BookingStatus::Active->value,
                BookingStatus::NoticePeriod->value,
            ])
            ->latestOfMany();
    }

    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Booking::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * @param  Builder<Guest>  $query
     * @return Builder<Guest>
     */
    public function scopeStaying(Builder $query): Builder
    {
        return $query->whereHas('bookings', fn (Builder $bookings) => $bookings->whereIn('status', [
            BookingStatus::Active->value,
            BookingStatus::NoticePeriod->value,
        ]));
    }

    public function scopeKycVerified(Builder $query): Builder
    {
        return $query->where('kyc_status', KycStatus::Verified->value);
    }

    /**
     * Hashes an Aadhaar number for storage/comparison. Digits and spaces only,
     * so "1234 5678 9012" and "123456789012" collide as they should.
     */
    public static function hashAadhaar(string $aadhaar): string
    {
        return hash('sha256', preg_replace('/\D/', '', $aadhaar) ?? '');
    }

    public static function aadhaarLast4(string $aadhaar): string
    {
        $digits = preg_replace('/\D/', '', $aadhaar) ?? '';

        return substr($digits, -4);
    }

    /**
     * Sets both the digest and the displayable tail in one call.
     */
    public function setAadhaarNumber(string $aadhaar): static
    {
        $this->adhaar_number_hash = static::hashAadhaar($aadhaar);
        $this->adhaar_last4 = static::aadhaarLast4($aadhaar);
        $this->id_proof_type = 'aadhaar';

        return $this;
    }

    public function isBlacklisted(): bool
    {
        return (bool) $this->is_blacklisted;
    }

    public function canBeAllocatedBed(): bool
    {
        return ! $this->isBlacklisted() && $this->kyc_status->allowsAllocation();
    }

    /**
     * Total outstanding across every live booking of this guest.
     */
    public function outstandingBalance(): float
    {
        return (float) Invoice::query()
            ->whereIn('booking_id', $this->bookings()->select('id'))
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->sum('total_due');
    }

    public function getLabelAttribute(): string
    {
        return $this->full_name.' ('.$this->phone.')';
    }

    public function getMaskedAadhaarAttribute(): string
    {
        return $this->adhaar_last4 ? 'XXXX XXXX '.$this->adhaar_last4 : '—';
    }
}
