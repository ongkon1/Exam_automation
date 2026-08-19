<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        $students = User::students()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            })
            ->withCount('results')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('teacher.students.index', compact('students', 'search'));
    }

    public function create(): View
    {
        return view('teacher.students.create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'role' => User::ROLE_STUDENT,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('teacher.students.index')
            ->with('success', 'Student created successfully.');
    }

    public function show(User $student): View
    {
        $this->ensureIsStudent($student);

        $student->load(['results' => fn ($q) => $q->latest('exam_date')->latest('id')]);

        return view('teacher.students.show', compact('student'));
    }

    public function edit(User $student): View
    {
        $this->ensureIsStudent($student);

        return view('teacher.students.edit', compact('student'));
    }

    public function update(UpdateStudentRequest $request, User $student): RedirectResponse
    {
        $this->ensureIsStudent($student);

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $student->update($data);

        return redirect()->route('teacher.students.index')
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(User $student): RedirectResponse
    {
        $this->ensureIsStudent($student);

        $student->delete();

        return redirect()->route('teacher.students.index')
            ->with('success', 'Student deleted successfully.');
    }

    /**
     * Guard the student routes against being pointed at a teacher account.
     */
    protected function ensureIsStudent(User $student): void
    {
        abort_unless($student->isStudent(), 404);
    }
}
