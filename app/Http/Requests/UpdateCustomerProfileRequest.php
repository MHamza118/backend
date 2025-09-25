<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
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
        $customerId = $this->route('customer') ?? $this->route('id');
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 
                'string', 
                'email:rfc,dns', 
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId)
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^[\+]?[1-9][\d]{0,14}$/', 'max:20'],
            'locations' => ['sometimes', 'array', 'min:1'],
            'locations.*.name' => ['required_with:locations', 'string', 'max:255'],
            'locations.*.is_home' => ['sometimes', 'boolean'],
            'preferences' => ['sometimes', 'array'],
            'preferences.notifications' => ['sometimes', 'boolean'],
            'preferences.marketing' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'Name must be a valid string.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already taken by another customer.',
            'phone.regex' => 'Please enter a valid phone number.',
            'locations.min' => 'At least one location is required.',
            'locations.*.name.required_with' => 'Location name is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one location is marked as home if locations are provided
            $locations = $this->get('locations');
            if ($locations && is_array($locations)) {
                $hasHome = collect($locations)->contains('is_home', true);
                
                if (!$hasHome && count($locations) > 0) {
                    $validator->errors()->add('locations', 'At least one location must be marked as home.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from name and email if provided
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        
        if ($this->has('email')) {
            $this->merge(['email' => trim(strtolower($this->email))]);
        }

        // Clean phone number if provided
        if ($this->has('phone') && $this->phone) {
            $cleanPhone = preg_replace('/[^\+\d]/', '', $this->phone);
            $this->merge(['phone' => $cleanPhone]);
        }
    }
}
