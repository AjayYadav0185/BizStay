<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a bed allotment. The authoritative availability check happens
 * inside BedAllocationService behind a row lock; this request only guarantees
 * the shape and sanity of the input.
 */
final class AllocateBedRequest extends FormRequest
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
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'check_in_date' => ['required', 'date'],
            'expected_check_out_date' => ['nullable', 'date', 'after_or_equal:check_in_date'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'security_deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,28'],
            'food_included' => ['boolean'],
            'bypass_kyc_check' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_check_out_date.after_or_equal' => 'The expected check-out date cannot be before the check-in date.',
        ];
    }
}
