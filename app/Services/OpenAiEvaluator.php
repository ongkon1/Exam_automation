<?php

namespace App\Services;

use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\TeacherSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEvaluator
{
    protected const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Ask OpenAI to evaluate a single exam result using the teacher's saved prompts.
     *
     * @throws RuntimeException when the API key is missing or the call fails
     */
    public function evaluate(Result $result, TeacherSetting $settings): string
    {
        return $this->request([
            ['role' => 'system', 'content' => $settings->system_prompt],
            ['role' => 'user', 'content' => $settings->evaluation_prompt."\n\n".$this->renderResult($result)],
        ]);
    }

    /**
     * Send a chat completion and return the assistant's message content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  bool  $json  request a JSON object back rather than free text
     *
     * @throws RuntimeException when the API key is missing or the call fails
     */
    protected function request(array $messages, bool $json = false): string
    {
        $apiKey = config('services.openai.key');

        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not set in your .env file.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->acceptJson()
            ->post(self::ENDPOINT, array_filter([
                'model' => config('services.openai.model'),
                'temperature' => 0.7,
                'messages' => $messages,
                'response_format' => $json ? ['type' => 'json_object'] : null,
            ]));

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error.message') ?? "OpenAI returned HTTP {$response->status()}."
            );
        }

        $content = $response->json('choices.0.message.content');

        if (blank($content)) {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return trim($content);
    }

    /**
     * Summarise a call transcript.
     *
     * The teacher's **evaluation prompt** is used as the system prompt here — it is the
     * field that describes how the call should be written up — and the raw transcript is
     * the only user content.
     *
     * @throws RuntimeException when the API key is missing or the call fails
     */
    public function summarise(ExamTranscript $transcript, TeacherSetting $settings): string
    {
        return $this->request([
            ['role' => 'system', 'content' => $settings->evaluation_prompt],
            ['role' => 'user', 'content' => $transcript->contentForAi()],
        ]);
    }

    /**
     * Score a voice-exam transcript and write feedback, using the teacher's saved prompts.
     *
     * Unlike a written result, a transcript carries no marks, so the model is asked to
     * award them. Returns ['marks_obtained' => float, 'feedback' => string].
     *
     * @return array{marks_obtained: float, feedback: string}
     *
     * @throws RuntimeException when the API key is missing or the call fails
     */
    public function evaluateTranscript(ExamTranscript $transcript, TeacherSetting $settings): array
    {
        $fullMarks = (int) config('webcall.full_marks');

        $instruction = $settings->evaluation_prompt."\n\n"
            .$this->renderTranscript($transcript, $fullMarks)."\n\n"
            .'Respond with a JSON object containing exactly two keys: "marks_obtained", a number '
            ."between 0 and {$fullMarks} scoring this student's performance, and \"feedback\", "
            .'the written feedback for the student.';

        $payload = $this->request([
            ['role' => 'system', 'content' => $settings->system_prompt],
            ['role' => 'user', 'content' => $instruction],
        ], json: true);

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! array_key_exists('marks_obtained', $decoded) || blank($decoded['feedback'] ?? null)) {
            throw new RuntimeException('OpenAI did not return the expected marks_obtained and feedback fields.');
        }

        if (! is_numeric($decoded['marks_obtained'])) {
            throw new RuntimeException('OpenAI returned a non-numeric score.');
        }

        // Clamp rather than reject: a model that overshoots the range should not lose the transcript.
        $marks = max(0, min($fullMarks, round((float) $decoded['marks_obtained'], 2)));

        return [
            'marks_obtained' => $marks,
            'feedback' => trim((string) $decoded['feedback']),
        ];
    }

    /**
     * Render a voice-exam transcript as a plain-text block for the model to reason over.
     */
    protected function renderTranscript(ExamTranscript $transcript, int $fullMarks): string
    {
        $student = $transcript->student;

        $lines = [
            'Student name: '.($student?->name ?? 'Unknown'),
            'Roll number: '.($student?->roll_number ?: 'N/A'),
            'Class: '.($student?->class_name ?: 'N/A'),
            'Subject: '.$transcript->subject,
            'Exam format: spoken exam conducted over a web call',
            'Marks available: '.$fullMarks,
        ];

        return "Voice exam details:\n".implode("\n", $lines)
            ."\n\nCall transcript:\n".$transcript->contentForAi();
    }

    /**
     * Render the result as a plain-text block for the model to reason over.
     */
    protected function renderResult(Result $result): string
    {
        $student = $result->student;

        $lines = [
            'Student name: '.$student->name,
            'Roll number: '.($student->roll_number ?: 'N/A'),
            'Class: '.($student->class_name ?: 'N/A'),
            'Exam: '.$result->exam_name,
            'Subject: '.$result->subject,
            'Exam date: '.($result->exam_date?->toFormattedDateString() ?: 'N/A'),
            'Marks obtained: '.$result->marks_obtained.' out of '.$result->full_marks,
            'Percentage: '.$result->percentage.'%',
            'Grade: '.($result->grade ?: 'N/A'),
            'Teacher remarks: '.($result->remarks ?: 'None'),
        ];

        return "Exam result data:\n".implode("\n", $lines);
    }
}
