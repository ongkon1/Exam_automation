<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\TeacherSetting;
use App\Services\OpenAiEvaluator;
use App\Services\SpeaklarClient;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateExamTranscript
{
    use Dispatchable, InteractsWithQueue;

    public function __construct(public ExamTranscript $transcript) {}

    /**
     * Score the transcript with the AI and turn it into a result row, so a voice exam
     * shows up alongside written exams on both dashboards.
     */
    public function handle(OpenAiEvaluator $evaluator): void
    {
        $transcript = $this->transcript->fresh();

        if (! $transcript || $transcript->status === ExamTranscript::STATUS_EVALUATED) {
            return;
        }

        // The callback identifies the call by id only, so when nothing matched at
        // intake, ask Speaklar who was on the call.
        if (! $transcript->student_id) {
            $this->resolveFromProvider($transcript);
            $transcript->refresh();
        }

        $student = $transcript->student;

        if (! $student) {
            $transcript->update(['status' => ExamTranscript::STATUS_UNMATCHED]);

            return;
        }

        $settings = $this->promptsFor($transcript);

        if (! $settings) {
            $transcript->markFailed('No teacher has saved both a system prompt and an evaluation prompt.');

            return;
        }

        try {
            $evaluation = $evaluator->evaluateTranscript($transcript, $settings);
        } catch (Throwable $e) {
            report($e);
            $transcript->markFailed($e->getMessage());

            return;
        }

        $fullMarks = (int) config('webcall.full_marks');
        $percentage = $fullMarks > 0 ? $evaluation['marks_obtained'] / $fullMarks * 100 : 0.0;

        $result = Result::create([
            'student_id' => $student->id,
            'exam_name' => config('webcall.exam_name'),
            'subject' => $transcript->subject ?: config('webcall.subject'),
            'exam_date' => now()->toDateString(),
            'full_marks' => $fullMarks,
            'marks_obtained' => $evaluation['marks_obtained'],
            'grade' => Result::gradeFor(round($percentage, 2)),
            'ai_feedback' => $evaluation['feedback'],
            'evaluated_at' => now(),
            'created_by' => $settings->user_id,
        ]);

        $transcript->update([
            'result_id' => $result->id,
            'status' => ExamTranscript::STATUS_EVALUATED,
            'failure_reason' => null,
            'evaluated_at' => now(),
        ]);

        Log::info('Voice exam transcript evaluated.', [
            'transcript_id' => $transcript->id,
            'result_id' => $result->id,
        ]);
    }

    /**
     * Recover the student for a transcript that arrived with only a call id.
     *
     * Speaklar's status endpoint gives the number the student called from. The call
     * session they opened when they pressed Start Exam is claimed at the same time, so
     * one call cannot be counted against two sessions.
     */
    protected function resolveFromProvider(ExamTranscript $transcript): void
    {
        if (blank($transcript->external_id)) {
            return;
        }

        $call = app(SpeaklarClient::class)->findCall($transcript->external_id);

        if (! $call) {
            return;
        }

        $updates = [];

        // The callback's transcript is authoritative, but fall back to the API's copy.
        if (blank($transcript->transcript) && filled($call['transcript'])) {
            $updates['transcript'] = $call['transcript'];
        }

        $student = $transcript->student ?? PhoneNumber::findStudent($call['phone']);

        if (! $student) {
            Log::warning('Speaklar call resolved to no known student.', [
                'transcript_id' => $transcript->id,
                'phone' => $call['phone'],
            ]);

            $transcript->update($updates + ['phone' => PhoneNumber::normalize($call['phone']) ?? $transcript->phone]);

            return;
        }

        $updates['student_id'] = $student->id;
        $updates['phone'] = PhoneNumber::normalize($call['phone']) ?? $student->phone;

        $session = CallSession::openFor($student);

        if ($session) {
            if (blank($transcript->subject) && filled($session->subject)) {
                $updates['subject'] = $session->subject;
            }

            $session->update([
                'matched_at' => now(),
                'call_id' => $session->call_id ?: $transcript->external_id,
            ]);
        }

        $transcript->update($updates);
    }

    /**
     * Prompts come from the teacher who created the student, falling back to any
     * teacher who has both prompts filled in.
     */
    protected function promptsFor(ExamTranscript $transcript): ?TeacherSetting
    {
        $ownTeacher = $transcript->student->created_by
            ? TeacherSetting::where('user_id', $transcript->student->created_by)->first()
            : null;

        if ($ownTeacher?->isReadyForEvaluation()) {
            return $ownTeacher;
        }

        return TeacherSetting::query()
            ->whereNotNull('system_prompt')->where('system_prompt', '!=', '')
            ->whereNotNull('evaluation_prompt')->where('evaluation_prompt', '!=', '')
            ->orderBy('user_id')
            ->first();
    }
}
