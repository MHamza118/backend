<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeQuestionnaireRequest extends FormRequest
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
        $rules = [
            'responses' => 'required',
        ];
        
        // Handle FormData submissions (when files are present)
        if ($this->hasFile('file_0') || $this->hasFile('file_1') || $this->hasFile('file_2') || 
            $this->hasFile('file_3') || $this->hasFile('file_4') || $this->hasFile('file_5') ||
            $this->hasFile('file_6') || $this->hasFile('file_7') || $this->hasFile('file_8') ||
            $this->hasFile('file_9') || $this->hasFile('file_10') || $this->hasFile('file_11')) {
            // When files are present, responses comes as JSON string
            $rules['responses'] = 'required|string';
            
            // Add file validation rules
            for ($i = 0; $i < 20; $i++) {
                $rules["file_{$i}"] = 'sometimes|file|max:10240|mimes:pdf,jpg,jpeg,png,gif,doc,docx';
            }
        } else {
            // Normal array submission
            $rules['responses'] = 'required|array';
            $rules['responses.*'] = 'required';
        }
        
        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responses.required' => 'Questionnaire responses are required',
            'responses.*.required' => 'All questions must be answered'
        ];
    }
}
