<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebCallTranscriptRequest;
use App\Jobs\EvaluateExamTranscript;
use App\Models\CallSession;
use App\Models\ExamTranscript;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebCallTranscriptController extends Controller
{
    /**
     * Receive the transcript a voice provider posts when an exam call ends.
     */
    public function __invoke(StoreWebCallTranscriptRequest $request): JsonResponse
    {
        $data = $request->validated();
        $callId = $data['call_id'] ?? null;

        // A provider that retries the callback must not create a second transcript.
        if ($callId && $existing = ExamTranscript::where('external_id', $callId)->first()) {
            Log::info('Duplicate voice exam transcript callback received.', [
                'transcript_id' => $existing->id,
                'call_id' => $callId,
            ]);
            return response()->json([
                'status' => 'duplicate',
                'transcript_id' => $existing->id,
            ]);
        }

        // The student registered this call id when they started the exam, which tells us
        // both who they are and which subject they picked. Phone matching is the fallback.
        $session = $callId ? CallSession::where('call_id', $callId)->first() : null;

        $student = $session?->student ?? PhoneNumber::findStudent($data['phone'] ?? null);

        // Voice exams are recorded per student, so a subject is optional. Left null when
        // unknown so a session found later can still supply one; the configured label is
        // applied when the result is written.
        $subject = $session?->subject ?: (trim((string) ($data['subject'] ?? '')) ?: null);

        $transcript = ExamTranscript::create([
            'student_id' => $student?->id,
            'phone' => PhoneNumber::normalize($data['phone'] ?? null) ?? $student?->phone ?? '',
            'subject' => $subject,
            'transcript' => $data['transcript'],
            'summary' => $data['summary'] ?? null,
            'call_result' => $data['result'] ?? null,
            'external_id' => $callId,
            'status' => ExamTranscript::STATUS_PENDING,
            'payload' => $request->except('transcript'),
        ]);

        $resolved = (bool) $student;

        // Nothing matched at intake, and there is no call id to look the call up by
        // either — there is nothing further the job could do.
        if (! $resolved && blank($callId)) {
            $transcript->update(['status' => ExamTranscript::STATUS_UNMATCHED]);

            Log::warning('Voice exam transcript could not be attributed.', [
                'transcript_id' => $transcript->id,
                'phone' => $transcript->phone,
                'subject' => $transcript->subject,
            ]);

            return response()->json([
                'status' => 'unmatched',
                'transcript_id' => $transcript->id,
                'message' => 'No student matches that phone number. The transcript was stored for review.',
            ], 202);
        }

        // Runs in this process once the response has been sent, so the provider is not
        // kept waiting on the Speaklar lookup or the OpenAI call. Swap to dispatch() to
        // use a real queue worker.
        EvaluateExamTranscript::dispatchAfterResponse($transcript);

        return response()->json([
            'status' => $resolved ? 'accepted' : 'pending',
            'transcript_id' => $transcript->id,
            'message' => $resolved ? null : 'Looking the call up to identify the student.',
        ], 202);
    }
}
