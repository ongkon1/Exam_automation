<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function index(Request $request): View
    {
        $results = $request->user()->results()
            ->latest('exam_date')
            ->latest('id')
            ->paginate(15);

        return view('student.results.index', compact('results'));
    }

    public function show(Request $request, Result $result): View
    {
        // Role middleware is not enough — a student must never read another student's result.
        abort_unless($result->student_id === $request->user()->id, 403);

        return view('student.results.show', compact('result'));
    }
}
