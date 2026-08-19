<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = $request->user();
        $results = $student->results()->get();

        $percentages = $results->map(fn ($result) => $result->percentage);

        return view('student.dashboard', [
            'student' => $student,
            'totalResults' => $results->count(),
            'averagePercentage' => $percentages->isNotEmpty() ? round($percentages->avg(), 2) : null,
            'bestResult' => $results->sortByDesc(fn ($r) => $r->percentage)->first(),
            'worstResult' => $results->sortBy(fn ($r) => $r->percentage)->first(),
            'recentResults' => $student->results()->latest('exam_date')->latest('id')->take(5)->get(),
        ]);
    }
}
