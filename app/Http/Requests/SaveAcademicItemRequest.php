<?php

namespace App\Http\Requests;

use App\Models\AcademicItem;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademicItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item ? $this->user()->can('update', $item) : $this->user() !== null;
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
            'description' => ['required', 'string', 'max:10000'],
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('is_archived', false)],
            'type' => ['required', Rule::in(AcademicItem::TYPES)],
            'technology' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:100'],
            'team_members' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:255'],
            'visibility' => ['required', Rule::in(['private', 'course', 'public'])],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,txt,zip,pptx,xlsx,csv', 'extensions:pdf,docx,txt,zip,pptx,xlsx,csv', 'max:20480'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->has('course_id')) {
                $course = Course::find($this->integer('course_id'));
                if (! $course?->isAccessibleTo($this->user())) {
                    $validator->errors()->add('course_id', 'You can upload only to courses you belong to.');
                }
            }
        }];
    }
}
