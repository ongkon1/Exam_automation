<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallSessionController extends Controller
{
    /**
     * Claim a call id for the student who just started a voice exam.
     */
    /**
     * Open a call session as the student starts an exam.
     *
     * Voice exams are recorded per student, so there is no subject to capture — this
     * simply records that the student started a call, and their number for reference.
     * The transcript callback is reconciled later using the number Speaklar reports.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'call_id' => ['nullable', 'string', 'max:255'],
        ]);

        $student = $request->user();
        $callId = $data['call_id'] ?? null;

        if ($callId) {
            $existing = CallSession::where('call_id', $callId)->first();

            // A call id is claimed once. Without this, anyone could post another
            // student's call id and have their marks attributed to themselves.
            if ($existing && $existing->student_id !== $student->id) {
                return response()->json(['message' => 'That call is already registered.'], 409);
            }

            $session = CallSession::updateOrCreate(['call_id' => $callId], [
                'student_id' => $student->id,
                'phone' => $student->phone,
                'subject' => $data['subject'] ?? null,
                'started_at' => $existing?->started_at ?? now(),
            ]);

            return response()->json(['status' => 'registered', 'id' => $session->id], 201);
        }

        // Reuse a session the student just opened rather than stacking duplicates if
        // the widget fires twice for one call.
        $session = CallSession::openFor($student);

        if ($session && $session->started_at?->gt(now()->subMinutes(2))) {
            $session->update(['subject' => $data['subject'] ?? $session->subject]);
        } else {
            $session = CallSession::create([
                'student_id' => $student->id,
                'phone' => $student->phone,
                'subject' => $data['subject'] ?? null,
                'started_at' => now(),
            ]);
        }

        return response()->json(['status' => 'registered', 'id' => $session->id], 201);
    }

    /**
     * Mark the call finished. The transcript callback usually arrives shortly after.
     */
    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id' => ['nullable', 'string', 'max:255'],
        ]);

        $student = $request->user();

        $session = filled($data['call_id'] ?? null)
            ? CallSession::where('call_id', $data['call_id'])->where('student_id', $student->id)->first()
            : CallSession::openFor($student);

        if (! $session) {
            return response()->json(['message' => 'Unknown call.'], 404);
        }

        $session->update(['ended_at' => now()]);

        return response()->json(['status' => 'ended']);
    }
}
