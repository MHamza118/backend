<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'category' => ['sometimes', 'string', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['sometimes', 'string', Rule::in(array_keys(Ticket::PRIORITIES))],
            'status' => ['sometimes', 'string', Rule::in(array_keys(Ticket::STATUSES))],
            'location' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Ticket title cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 2000 characters',
            'category.in' => 'Invalid category selected',
            'priority.in' => 'Invalid priority selected',
            'status.in' => 'Invalid status selected',
            'location.max' => 'Location cannot exceed 255 characters',
        ];
    }
}
