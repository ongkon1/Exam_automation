<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebCallTranscriptRequest;
use App\Jobs\EvaluateExamTranscript;
use App\Models\CallSession;
use App\Models\ExamTranscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebCallTranscriptController extends Controller
{
    /**
     * Receive the callback a voice provider posts when an exam call ends.
     *
     * Only the call id matters. Who sat the exam is decided by looking that id up on
     * Speaklar — never by anything in this payload — so an open endpoint cannot be used
     * to write marks against a student who was never called.
     */
    public function __invoke(StoreWebCallTranscriptRequest $request): JsonResponse
    {
        Log::info('Voice exam transcript callback received.', [
            'call_id' => $request->input('call_id'),
            'result' => $request->input('result'),
        ]);

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

        // Only set if some future flow registered the call id up front; normally null,
        // and the provider lookup fills everything in.
        $session = $callId ? CallSession::where('call_id', $callId)->first() : null;

        $transcript = ExamTranscript::create([
            'student_id' => $session?->student_id,
            'phone' => $session?->phone ?? '',
            'subject' => $session?->subject,
            'transcript' => $data['transcript'] ?? '',
            'summary' => $data['summary'] ?? null,
            'call_result' => $data['result'] ?? null,
            'external_id' => $callId,
            'status' => ExamTranscript::STATUS_PENDING,
            'payload' => $request->except('transcript'),
        ]);

        // With no call id there is nothing to look the call up by, so it can never be
        // attributed. Kept for review rather than dropped.
        if (! $callId && ! $session) {
            $transcript->update(['status' => ExamTranscript::STATUS_UNMATCHED]);

            Log::warning('Voice exam callback arrived without a call id.', [
                'transcript_id' => $transcript->id,
            ]);

            return response()->json([
                'status' => 'unmatched',
                'transcript_id' => $transcript->id,
                'message' => 'No call_id was supplied, so this call cannot be matched to a student.',
            ], 202);
        }

        // Runs in this process once the response has been sent, so the provider is not
        // kept waiting on the Speaklar lookup or the OpenAI call. Swap to dispatch() to
        // use a real queue worker.
        EvaluateExamTranscript::dispatchAfterResponse($transcript);

        return response()->json([
            'status' => 'accepted',
            'transcript_id' => $transcript->id,
        ], 202);
    }
}
