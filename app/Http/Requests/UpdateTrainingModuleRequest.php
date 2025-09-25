<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingModuleRequest extends FormRequest
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
        $moduleId = $this->route('id');
        
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'qr_code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('training_modules', 'qr_code')->ignore($moduleId)
            ],
            'video_url' => 'nullable|url|max:500',
            'content' => 'sometimes|required|string',
            'duration' => 'nullable|string|max:50',
            'category' => 'sometimes|required|string|max:100',
            'active' => 'boolean'
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
            'title.required' => 'Training module title is required',
            'description.required' => 'Training module description is required',
            'content.required' => 'Training module content is required',
            'category.required' => 'Training module category is required',
            'qr_code.unique' => 'QR code must be unique',
            'video_url.url' => 'Video URL must be a valid URL'
        ];
    }
}