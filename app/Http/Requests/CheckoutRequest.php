<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Captures the manager's choices at the "Initiate Check-Out" step. Every
 * monetary field here is an input (damages, goodwill credit, collection
 * channel); the settlement maths itself is never accepted from the client.
 */
final class CheckoutRequest extends FormRequest
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
            'checkout_date' => ['required', 'date'],
            'damages' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'other_credits' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['nullable', Rule::in(array_keys(PaymentMethod::collectionOptions()))],
            'refund_method' => ['nullable', Rule::in(array_keys(PaymentMethod::collectionOptions()))],
            'transaction_id' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checkout_date.required' => 'Choose the date the guest is checking out on.',
        ];
    }
}
