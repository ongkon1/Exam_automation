<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Services\OpenAiEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EvaluationController extends Controller
{
    public function __invoke(Request $request, Result $result, OpenAiEvaluator $evaluator): RedirectResponse
    {
        $settings = $request->user()->teacherSetting;

        if (! $settings || ! $settings->isReadyForEvaluation()) {
            return back()->with('error', 'Save your System prompt and Evaluation prompt in Settings before running an evaluation.');
        }

        try {
            $feedback = $evaluator->evaluate($result->load('student'), $settings);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Evaluation failed: '.$e->getMessage());
        }

        $result->update([
            'ai_feedback' => $feedback,
            'evaluated_at' => now(),
        ]);

        return back()->with('success', 'AI evaluation generated for '.$result->student->name.'.');
    }
}
