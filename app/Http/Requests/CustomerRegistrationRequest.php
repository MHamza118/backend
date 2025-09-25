<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CustomerRegistrationRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'regex:/^[\+]?[1-9][\d]{0,14}$/', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*.name' => ['required', 'string', 'max:255'],
            'locations.*.is_home' => ['boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'phone.regex' => 'Please enter a valid phone number.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'locations.required' => 'At least one location is required.',
            'locations.min' => 'At least one location is required.',
            'locations.*.name.required' => 'Location name is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one location is marked as home
            $locations = $this->get('locations', []);
            $hasHome = collect($locations)->contains('is_home', true);
            
            if (!$hasHome && count($locations) > 0) {
                // Automatically set the first location as home
                $locations[0]['is_home'] = true;
                $this->merge(['locations' => $locations]);
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from name and email
        $this->merge([
            'name' => trim($this->name ?? ''),
            'email' => trim(strtolower($this->email ?? '')),
        ]);

        // Clean phone number
        if ($this->phone) {
            $cleanPhone = preg_replace('/[^\+\d]/', '', $this->phone);
            $this->merge(['phone' => $cleanPhone]);
        }
    }
}
