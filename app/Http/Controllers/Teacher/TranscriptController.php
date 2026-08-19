<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ExamTranscript;
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
}
