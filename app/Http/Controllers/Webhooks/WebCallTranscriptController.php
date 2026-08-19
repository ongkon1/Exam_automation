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
            return response()->json([
                'status' => 'duplicate',
                'transcript_id' => $existing->id,
            ]);
        }

        // The student registered this call id when they started the exam, which tells us
        // both who they are and which subject they picked. Phone matching is the fallback.
        $session = $callId ? CallSession::where('call_id', $callId)->first() : null;

        $student = $session?->student ?? PhoneNumber::findStudent($data['phone'] ?? null);
        $subject = $session?->subject ?? trim((string) ($data['subject'] ?? ''));

        $transcript = ExamTranscript::create([
            'student_id' => $student?->id,
            'phone' => PhoneNumber::normalize($data['phone'] ?? null) ?? $student?->phone ?? '',
            'subject' => $subject,
            'transcript' => $data['transcript'],
            'external_id' => $callId,
            'status' => $student && $subject !== ''
                ? ExamTranscript::STATUS_PENDING
                : ExamTranscript::STATUS_UNMATCHED,
            'payload' => $request->except('transcript'),
        ]);

        if (! $student || $subject === '') {
            Log::warning('Voice exam transcript could not be attributed.', [
                'transcript_id' => $transcript->id,
                'call_id' => $callId,
                'phone' => $transcript->phone,
                'subject' => $transcript->subject,
            ]);

            return response()->json([
                'status' => 'unmatched',
                'transcript_id' => $transcript->id,
                'message' => $student
                    ? 'No subject could be resolved for that call. The transcript was stored for review.'
                    : 'No student matches that call id or phone number. The transcript was stored for review.',
            ], 202);
        }

        // Runs in this process once the response has been sent, so the provider is not
        // kept waiting on the OpenAI call. Swap to dispatch() to use a real queue worker.
        EvaluateExamTranscript::dispatchAfterResponse($transcript);

        return response()->json([
            'status' => 'accepted',
            'transcript_id' => $transcript->id,
        ], 202);
    }
}
