<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketResponseRequest extends FormRequest
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
            'message' => 'required|string|max:2000',
            'internal' => 'sometimes|boolean',
            'update_status' => 'sometimes|boolean',
            'new_status' => [
                'required_if:update_status,true',
                'string',
                Rule::in(array_keys(Ticket::STATUSES))
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Response message is required',
            'message.max' => 'Response message cannot exceed 2000 characters',
            'new_status.required_if' => 'New status is required when updating ticket status',
            'new_status.in' => 'Invalid status selected',
        ];
    }
}
