<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudentProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('student.profile', [
            'student' => $request->user(),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('student.profile-edit', [
            'student' => $request->user(),
        ]);
    }

    public function update(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Roll number and class stay teacher-controlled, so they are never part of $data.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $request->user()->update($data);

        return redirect()->route('student.profile')
            ->with('success', 'Your profile has been updated.');
    }
}
