<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTrainingModuleRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'due_date' => 'nullable|date|after:today',
            'assignment_type' => 'required|in:immediate,scheduled',
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'At least one employee must be selected',
            'employee_ids.array' => 'Employee IDs must be provided as an array',
            'employee_ids.*.exists' => 'Selected employee does not exist',
            'due_date.after' => 'Due date must be in the future',
            'assignment_type.required' => 'Assignment type is required',
            'assignment_type.in' => 'Assignment type must be either immediate or scheduled'
        ];
    }
}