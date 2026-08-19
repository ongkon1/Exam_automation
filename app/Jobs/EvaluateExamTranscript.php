<?php

namespace App\Jobs;

use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\TeacherSetting;
use App\Services\OpenAiEvaluator;
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
            'subject' => $transcript->subject,
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
