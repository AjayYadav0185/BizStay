<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\KycStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for guest validation. The Filament resource imports
 * these rules so the panel and any future API/import path validate identically.
 */
final class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Panel access is enforced by Filament's auth middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignoreId = $this->route('record')?->id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'max:20',
                'regex:/^[0-9+\-\s]{10,20}$/',
                Rule::unique('guests', 'phone')->ignore($ignoreId),
            ],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],

            'adhaar_number' => ['nullable', 'string', 'regex:/^\d{12}$/'],
            'id_proof_type' => ['required', Rule::in(['aadhaar', 'pan', 'passport', 'dl'])],
            'id_proof_number' => ['nullable', 'string', 'max:50'],

            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'kyc_documents' => ['nullable', 'array'],
            'kyc_documents.*' => ['string', 'max:255'],

            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'home_city' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:150'],

            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:40'],

            'is_blacklisted' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'adhaar_number.regex' => 'The Aadhaar number must be exactly 12 digits.',
            'phone.regex' => 'Enter a valid contact number (10-20 digits).',
        ];
    }
}
