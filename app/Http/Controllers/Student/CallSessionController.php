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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
        ]);

        $existing = CallSession::where('call_id', $data['call_id'])->first();

        // A call id is claimed once. Without this, anyone could post another student's
        // call id and have their transcript — and marks — attributed to themselves.
        if ($existing && $existing->student_id !== $request->user()->id) {
            return response()->json(['message' => 'That call is already registered.'], 409);
        }

        $session = CallSession::updateOrCreate(
            ['call_id' => $data['call_id']],
            [
                'student_id' => $request->user()->id,
                'subject' => $data['subject'],
                'started_at' => $existing?->started_at ?? now(),
            ],
        );

        return response()->json(['status' => 'registered', 'id' => $session->id], 201);
    }

    /**
     * Mark the call finished. The transcript callback usually arrives shortly after.
     */
    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id' => ['required', 'string', 'max:255'],
        ]);

        $session = CallSession::where('call_id', $data['call_id'])
            ->where('student_id', $request->user()->id)
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Unknown call.'], 404);
        }

        $session->update(['ended_at' => now()]);

        return response()->json(['status' => 'ended']);
    }
}
