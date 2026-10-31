<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'semester' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'teachers' => ['nullable', 'array'],
            'teachers.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)],
            'students' => ['nullable', 'array'],
            'students.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
