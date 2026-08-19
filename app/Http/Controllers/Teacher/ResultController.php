<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResultRequest;
use App\Http\Requests\UpdateResultRequest;
use App\Models\Result;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function index(Request $request): View
    {
        $studentId = $request->integer('student_id') ?: null;
        $subject = $request->string('subject')->trim()->toString();

        $results = Result::with('student')
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($subject !== '', fn ($q) => $q->where('subject', $subject))
            ->latest('exam_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('teacher.results.index', [
            'results' => $results,
            'students' => $this->studentOptions(),
            'subjects' => Result::query()->distinct()->orderBy('subject')->pluck('subject'),
            'studentId' => $studentId,
            'subject' => $subject,
            'settings' => $request->user()->teacherSetting,
        ]);
    }

    public function create(Request $request): View
    {
        return view('teacher.results.create', [
            'students' => $this->studentOptions(),
            'selectedStudent' => $request->integer('student_id') ?: null,
        ]);
    }

    public function store(StoreResultRequest $request): RedirectResponse
    {
        Result::create([
            ...$request->validated(),
            'grade' => $this->gradeFrom($request->validated()),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('teacher.results.index')
            ->with('success', 'Result recorded successfully.');
    }

    public function edit(Result $result): View
    {
        return view('teacher.results.edit', [
            'result' => $result,
            'students' => $this->studentOptions(),
        ]);
    }

    public function update(UpdateResultRequest $request, Result $result): RedirectResponse
    {
        $result->update([
            ...$request->validated(),
            'grade' => $this->gradeFrom($request->validated()),
        ]);

        return redirect()->route('teacher.results.index')
            ->with('success', 'Result updated successfully.');
    }

    public function destroy(Result $result): RedirectResponse
    {
        $result->delete();

        return redirect()->route('teacher.results.index')
            ->with('success', 'Result deleted successfully.');
    }

    /**
     * Derive the letter grade from the submitted marks.
     *
     * @param  array<string, mixed>  $data
     */
    protected function gradeFrom(array $data): string
    {
        $fullMarks = (int) $data['full_marks'];
        $percentage = $fullMarks > 0
            ? (float) $data['marks_obtained'] / $fullMarks * 100
            : 0.0;

        return Result::gradeFor(round($percentage, 2));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function studentOptions()
    {
        return User::students()->orderBy('name')->get(['id', 'name', 'roll_number']);
    }
}
