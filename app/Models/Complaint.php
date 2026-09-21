<?php

namespace App\Models;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    /** @use HasFactory<\Database\Factories\ComplaintFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'tenant_id', 'title', 'description', 'category',
        'priority', 'status', 'assigned_to', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'priority' => ComplaintPriority::class,
            'resolved_at' => 'datetime',
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
}
