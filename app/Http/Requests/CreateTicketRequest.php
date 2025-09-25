<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category' => ['required', 'string', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', 'string', Rule::in(array_keys(Ticket::PRIORITIES))],
            'location' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Ticket title is required',
            'title.max' => 'Ticket title cannot exceed 255 characters',
            'description.required' => 'Ticket description is required',
            'description.max' => 'Description cannot exceed 2000 characters',
            'category.required' => 'Category is required',
            'category.in' => 'Invalid category selected',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Invalid priority selected',
            'location.max' => 'Location cannot exceed 255 characters',
        ];
    }
}
