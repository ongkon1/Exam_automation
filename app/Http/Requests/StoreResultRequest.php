<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResultRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', User::ROLE_STUDENT),
            ],
            'exam_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'exam_date' => ['nullable', 'date'],
            'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'marks_obtained' => ['required', 'numeric', 'min:0', 'lte:full_marks'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marks_obtained.lte' => 'The marks obtained cannot be greater than the full marks.',
            'student_id.exists' => 'The selected student is invalid.',
        ];
    }
}
