<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeePersonalInfoRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Will be handled by middleware authentication
    }

    public function rules()
    {
        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255', 
            'phone' => 'sometimes|nullable|string|regex:/^\+?[1-9]\d{1,14}$/|max:20',
            'mailing_address' => 'sometimes|required|string|max:500',
            'requested_hours' => 'sometimes|required|integer|min:1|max:40',
            'emergency_contact' => 'sometimes|nullable|string|max:255',
            'emergency_phone' => 'sometimes|nullable|string|regex:/^\+?[1-9]\d{1,14}$/|max:20',
        ];
    }

    public function messages()
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'mailing_address.required' => 'Mailing address is required',
            'requested_hours.required' => 'Requested hours per week is required',
            'requested_hours.integer' => 'Requested hours must be a number',
            'requested_hours.min' => 'Requested hours must be at least 1',
            'requested_hours.max' => 'Requested hours cannot exceed 40',
            'phone.max' => 'Phone number must not exceed 20 characters',
            'emergency_phone.max' => 'Emergency phone number must not exceed 20 characters',
            'mailing_address.max' => 'Mailing address must not exceed 500 characters',
            'emergency_contact.max' => 'Emergency contact name must not exceed 255 characters',
        ];
    }
}
