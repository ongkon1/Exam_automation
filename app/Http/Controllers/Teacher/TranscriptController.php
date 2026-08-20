<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateExamTranscript;
use App\Models\ExamTranscript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->trim()->toString();

        $transcripts = ExamTranscript::with(['student', 'result'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('teacher.transcripts.index', [
            'transcripts' => $transcripts,
            'status' => $status,
            'unmatchedCount' => ExamTranscript::where('status', ExamTranscript::STATUS_UNMATCHED)->count(),
        ]);
    }

    public function show(ExamTranscript $transcript): View
    {
        $transcript->load(['student', 'result']);

        return view('teacher.transcripts.show', compact('transcript'));
    }

    /**
     * Run the evaluation again.
     *
     * `failure_reason` is a record of what happened last time, not a live check — once
     * the underlying problem is fixed (a missing API key, say) the stored message stays
     * until the test is re-evaluated. This is how a teacher clears it.
     */
    public function retry(ExamTranscript $transcript): RedirectResponse
    {
        $transcript->update([
            'status' => ExamTranscript::STATUS_PENDING,
            'failure_reason' => null,
        ]);

        // Run inline so the teacher sees the outcome on the page they land back on.
        dispatch_sync(new EvaluateExamTranscript($transcript));

        $transcript->refresh();

        return match ($transcript->status) {
            ExamTranscript::STATUS_EVALUATED => back()->with('success', 'Evaluation completed.'),
            ExamTranscript::STATUS_UNMATCHED => back()->with('error', 'Still no student matches this call.'),
            default => back()->with('error', 'Evaluation failed again: '.$transcript->failure_reason),
        };
    }
}
