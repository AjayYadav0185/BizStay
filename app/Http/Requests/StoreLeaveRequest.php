<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LeaveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Leave / absence request raised for a live booking. The food_opt_out flag is
 * what later converts into a mess deduction on the invoice.
 */
final class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'food_opt_out' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(LeaveStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The leave end date must be on or after the start date.',
        ];
    }
}
