<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebCallTranscriptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transcript' => ['required', 'string'],
            'call_id' => ['nullable', 'string', 'max:255'],

            // A known call id already tells us the student and the subject, so those
            // are only required when the callback has no call id to match on.
            'phone' => ['required_without:call_id', 'nullable', 'string', 'max:30'],
            'subject' => ['required_without:call_id', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Accept the field names voice providers commonly use so the callback does not
     * need a bespoke transformer on their side.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'phone' => $this->input('phone', $this->input('phone_number')),
            'subject' => $this->input('subject'),
            'transcript' => $this->input('transcript', $this->input('transcript_text')),
            'call_id' => $this->input('call_id', $this->input('id')),
        ], fn ($value) => $value !== null));
    }
}
