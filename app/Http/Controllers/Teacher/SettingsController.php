<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTeacherSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $teacher = $request->user();

        return view('teacher.settings.edit', [
            'teacher' => $teacher,
            'settings' => $teacher->teacherSetting()->firstOrCreate([]),
        ]);
    }

    public function update(UpdateTeacherSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $teacher = $request->user();

        $profile = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if (filled($data['password'] ?? null)) {
            $profile['password'] = $data['password'];
        }

        $teacher->update($profile);

        $teacher->teacherSetting()->updateOrCreate([], [
            'system_prompt' => $data['system_prompt'] ?? null,
            'evaluation_prompt' => $data['evaluation_prompt'] ?? null,
        ]);

        return redirect()->route('teacher.settings.edit')
            ->with('success', 'Settings saved successfully.');
    }
}
