<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebCallTranscriptRequest extends FormRequest
{
    /**
     * The callback needs nothing but a call id.
     *
     * Everything else — who was on the call, and the transcript itself — is read back
     * from Speaklar's status endpoint using that id. Anything the provider does send is
     * accepted and stored, but never trusted to decide whose result this is.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'call_id' => ['nullable', 'string', 'max:255'],
            'transcript' => ['nullable', 'string'],
            'result' => ['nullable', 'string', 'max:255'],
            // The provider may send a `summary`; it is accepted but neither validated
            // into the payload nor stored — the summary is generated from the transcript.
        ];
    }

    /**
     * Accept the field names voice providers commonly use for the call id.
     */
    protected function prepareForValidation(): void
    {
        $callId = $this->input('call_id', $this->input('id', $this->input('uuid')));

        $this->merge(array_filter([
            'call_id' => $callId,
            'transcript' => $this->input('transcript', $this->input('transcript_text')),
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'call_id.required' => 'A call_id is required — it is what identifies the call.',
        ];
    }
}
