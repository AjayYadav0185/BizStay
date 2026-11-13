<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gate register entry: someone who came to meet a resident.
 *
 * @property int $id
 * @property string $visitor_name
 * @property Carbon $checked_in_at
 * @property Carbon|null $checked_out_at
 */
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'guest_id', 'visitor_name', 'visitor_phone', 'relation', 'purpose', 'id_proof',
        'checked_in_at', 'checked_out_at', 'is_overnight', 'logged_by', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'is_overnight' => 'boolean',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeInside(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at');
    }

    public function isInside(): bool
    {
        return $this->checked_out_at === null;
    }

    public function checkOut(): void
    {
        if ($this->isInside()) {
            $this->forceFill(['checked_out_at' => now()])->save();
        }
    }
}
