@extends('layouts.app')

@section('title', 'Student Details')
@section('heading', $student->name)

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('teacher.results.create', ['student_id' => $student->id]) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Result
        </a>
        <a href="{{ route('teacher.students.edit', $student) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="{{ route('teacher.students.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Profile</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Roll number</dt><dd class="col-7">{{ $student->roll_number ?: '—' }}</dd>
                        <dt class="col-5">Class</dt><dd class="col-7">{{ $student->class_name ?: '—' }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $student->email }}</dd>
                        <dt class="col-5">Phone</dt><dd class="col-7">{{ $student->phone ?: '—' }}</dd>
                        <dt class="col-5">Date of birth</dt>
                        <dd class="col-7">{{ $student->date_of_birth?->toFormattedDateString() ?: '—' }}</dd>
                        <dt class="col-5">Address</dt><dd class="col-7">{{ $student->address ?: '—' }}</dd>
                        <dt class="col-5">Joined</dt><dd class="col-7">{{ $student->created_at->toFormattedDateString() }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Results</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th class="text-end">Marks</th>
                            <th class="text-end">%</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">AI</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($student->results as $result)
                            <tr>
                                <td>{{ $result->exam_name }}</td>
                                <td>{{ $result->subject }}</td>
                                <td>{{ $result->exam_date?->toFormattedDateString() ?: '—' }}</td>
                                <td class="text-end">{{ $result->marks_obtained }} / {{ $result->full_marks }}</td>
                                <td class="text-end">{{ $result->percentage }}%</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $result->gradeVariant() }}">{{ $result->grade }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($result->isEvaluated())
                                        <i class="bi bi-check-circle-fill text-success" title="Evaluated"></i>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No results recorded yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
