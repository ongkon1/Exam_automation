<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoiceExamController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = $request->user();

        return view('student.voice-exam', [
            'student' => $student,
            'subjects' => config('webcall.subjects'),
            // The call widget validates a website field before it will dial, so it is
            // filled with this app's host rather than shown to the student.
            'widgetWebsite' => $this->widgetWebsite(),
            'transcripts' => $student->examTranscripts()
                ->with('result')
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * A hostname the widget's URL validation accepts — it rejects ports and requires a dot.
     */
    protected function widgetWebsite(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return str_contains($host, '.') ? $host : $host.'.local';
    }
}
