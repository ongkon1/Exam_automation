@extends('layouts.app')

@section('title', 'Results')
@section('heading', 'Exam Results')

@section('actions')
    <a href="{{ route('teacher.results.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Result
    </a>
@endsection

@section('content')
    @php($canEvaluate = $settings && $settings->isReadyForEvaluation())

    @unless ($canEvaluate)
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            AI evaluation is disabled until you save both a <strong>System prompt</strong> and an
            <strong>Evaluation prompt</strong> in
            <a href="{{ route('teacher.settings.edit') }}" class="alert-link">Settings</a>.
        </div>
    @endunless

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <select name="student_id" class="form-select">
                        <option value="">All students</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected($studentId === $student->id)>
                                {{ $student->name }}{{ $student->roll_number ? " ({$student->roll_number})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="subject" class="form-select">
                        <option value="">All subjects</option>
                        @foreach ($subjects as $option)
                            <option value="{{ $option }}" @selected($subject === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
                </div>
                @if ($studentId || $subject !== '')
                    <div class="col-auto">
                        <a href="{{ route('teacher.results.index') }}" class="btn btn-link">Clear</a>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th class="text-end">Marks</th>
                        <th class="text-end">%</th>
                        <th class="text-center">Grade</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($results as $result)
                        <tr>
                            <td>
                                <a href="{{ route('teacher.students.show', $result->student) }}"
                                   class="text-decoration-none">{{ $result->student->name }}</a>
                                @if ($result->isEvaluated())
                                    <br>
                                    <span class="badge bg-success-subtle text-success-emphasis"
                                          title="Evaluated {{ $result->evaluated_at->diffForHumans() }}">
                                        <i class="bi bi-robot"></i> Evaluated
                                    </span>
                                @endif
                            </td>
                            <td>{{ $result->exam_name }}</td>
                            <td>{{ $result->subject }}</td>
                            <td>{{ $result->exam_date?->toFormattedDateString() ?: '—' }}</td>
                            <td class="text-end">{{ $result->marks_obtained }} / {{ $result->full_marks }}</td>
                            <td class="text-end">{{ $result->percentage }}%</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $result->gradeVariant() }}">{{ $result->grade }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('teacher.results.evaluate', $result) }}"
                                      class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" @disabled(! $canEvaluate)
                                            title="{{ $canEvaluate ? 'Generate AI evaluation' : 'Save your prompts in Settings first' }}">
                                        <i class="bi bi-robot"></i>
                                        {{ $result->isEvaluated() ? 'Re-evaluate' : 'AI Evaluate' }}
                                    </button>
                                </form>
                                <a href="{{ route('teacher.results.edit', $result) }}"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('teacher.results.destroy', $result) }}"
                                      class="d-inline" onsubmit="return confirm('Delete this result?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @if ($result->isEvaluated())
                            <tr class="table-light">
                                <td colspan="8" class="small">
                                    <strong class="text-success"><i class="bi bi-robot me-1"></i>AI feedback:</strong>
                                    <div class="ai-feedback mt-1">{{ $result->ai_feedback }}</div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No results found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $results->links() }}</div>
@endsection
