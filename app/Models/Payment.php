<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'tenant_id', 'type', 'amount', 'period_month', 'due_date',
        'paid_at', 'status', 'method', 'transaction_ref', 'collected_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'period_month' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
