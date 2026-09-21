<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id', 'category', 'amount', 'spent_on', 'vendor', 'method',
        'receipt_file', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'method' => PaymentMethod::class,
            'spent_on' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
