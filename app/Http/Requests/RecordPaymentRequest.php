<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for collecting money against an invoice.
 */
final class RecordPaymentRequest extends FormRequest
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
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'payment_method' => ['required', Rule::in(array_keys(PaymentMethod::collectionOptions()))],
            'payment_type' => ['nullable', Rule::enum(PaymentType::class)],
            'transaction_id' => [
                'nullable', 'string', 'max:80',
                Rule::requiredIf(fn (): bool => PaymentMethod::tryFrom((string) $this->input('payment_method'))?->requiresTransactionId() ?? false),
            ],
            'paid_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_id.required' => 'A transaction reference is required for UPI, card or net-banking collections.',
        ];
    }
}
