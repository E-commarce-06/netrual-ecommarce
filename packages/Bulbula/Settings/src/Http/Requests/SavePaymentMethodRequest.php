<?php

namespace Bulbula\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $type = $this->route('type');

        // Only validate core payment methods (COD and Bank Transfer)
        if ($type === 'cod') {
            return $this->codRules();
        } elseif ($type === 'bank_transfer') {
            return $this->bankTransferRules();
        }

        // For all extension payment gateways (Stripe, PayPal, Razorpay, etc.)
        // Use flexible validation - extensions handle their own validation
        return $this->flexibleGatewayRules();
    }

    /**
     * Flexible validation rules for any payment gateway extension
     * Allows any configuration structure without restrictions
     * Extensions should validate their specific requirements in their own code
     */
    private function flexibleGatewayRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'configuration' => ['nullable', 'array'],
            // Allow any configuration keys - extensions define their own structure
        ];
    }

    /**
     * Validation rules for Cash on Delivery method
     */
    private function codRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'max_order_amount' => ['required', 'numeric', 'min:0'],
            'min_order_amount_currency' => ['required', 'string', 'size:3'],
            'handling_fee' => ['required', 'numeric', 'min:0'],
            'handling_fee_type' => ['required', 'in:fixed,percentage'],
            'handling_fee_minimum' => ['required', 'numeric', 'min:0'],
            'handling_fee_maximum' => ['required', 'numeric', 'min:0'],
            'enabled_countries' => ['nullable', 'array'],
            'enabled_countries.*' => ['string', 'size:2'],
            'enabled_regions' => ['nullable', 'array'],
            'enabled_regions.*' => ['string'],
        ];
    }

    /**
     * Validation rules for Bank Transfer method
     */
    private function bankTransferRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'configuration' => ['required', 'array'],
            'configuration.account_destinations' => ['required', 'array', 'min:1'],
            'configuration.account_destinations.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'configuration.account_destinations.*.label' => ['required', 'string', 'max:100'],
            'configuration.account_destinations.*.provider' => ['required', 'string', 'max:100'],
            'configuration.account_destinations.*.type' => ['required', 'in:bank,wallet'],
            'configuration.account_destinations.*.account_name' => ['required', 'string', 'max:150'],
            'configuration.account_destinations.*.account_number' => ['required', 'string', 'max:100'],
            'configuration.account_destinations.*.instructions' => ['nullable', 'string', 'max:500'],
            'configuration.account_destinations.*.is_active' => ['required', 'boolean'],
            'configuration.min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'configuration.max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'configuration.handling_fee' => ['nullable', 'numeric', 'min:0'],
            'configuration.handling_fee_type' => ['nullable', 'in:flat,percentage'],
            'configuration.verification_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'configuration.allowed_countries' => ['nullable', 'array'],
            'configuration.allowed_countries.*' => ['string', 'regex:/^(\*|[A-Za-z]{2})$/'],
        ];
    }

    /**
     * Get custom messages for validation errors
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Payment method name is required',
            'name.max' => 'Payment method name cannot exceed 255 characters',
            'configuration.account_destinations.required' => 'Add at least one bank or mobile-wallet destination.',
            'configuration.account_destinations.min' => 'Add at least one bank or mobile-wallet destination.',
            'configuration.account_destinations.*.label.required' => 'Each destination needs a customer-facing label.',
            'configuration.account_destinations.*.provider.required' => 'Enter the bank or wallet provider name.',
            'configuration.account_destinations.*.account_name.required' => 'Enter the account holder name.',
            'configuration.account_destinations.*.account_number.required' => 'Enter the account or wallet number.',
            'min_order_amount.numeric' => 'Minimum order amount must be a valid number',
            'max_order_amount.numeric' => 'Maximum order amount must be a valid number',
            'handling_fee.numeric' => 'Handling fee must be a valid number',
            'handling_fee_type.in' => 'Handling fee type must be either fixed or percentage',
            'verification_days.min' => 'Verification days must be at least 1',
            'verification_days.max' => 'Verification days cannot exceed 90',
            'iban.string' => 'IBAN must be a valid string',
            'swift_code.string' => 'SWIFT code must be a valid string',
        ];
    }
}
