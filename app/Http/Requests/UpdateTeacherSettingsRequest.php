<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeacherSettingsRequest extends FormRequest
{
    /**
     * Compare the normalised form, since that is what is stored.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($this->user()->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'evaluation_prompt' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
