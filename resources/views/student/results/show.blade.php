@extends('layouts.app')

@section('title', 'Result Details')
@section('heading', $result->subject . ' — ' . $result->exam_name)

@section('actions')
    <a href="{{ route('student.results.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Result</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Exam</dt><dd class="col-7">{{ $result->exam_name }}</dd>
                        <dt class="col-5">Subject</dt><dd class="col-7">{{ $result->subject }}</dd>
                        <dt class="col-5">Exam date</dt>
                        <dd class="col-7">{{ $result->exam_date?->toFormattedDateString() ?: '—' }}</dd>
                        <dt class="col-5">Marks obtained</dt>
                        <dd class="col-7">{{ $result->marks_obtained }} out of {{ $result->full_marks }}</dd>
                        <dt class="col-5">Percentage</dt><dd class="col-7">{{ $result->percentage }}%</dd>
                        <dt class="col-5">Grade</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $result->gradeVariant() }}">{{ $result->grade }}</span>
                        </dd>
                    </dl>

                    <div class="progress mt-3" style="height: 8px;">
                        <div class="progress-bar bg-{{ $result->gradeVariant() }}"
                             style="width: {{ $result->percentage }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Teacher's Remarks</strong></div>
                <div class="card-body">
                    {{ $result->remarks ?: 'No remarks were added for this result.' }}
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-robot me-1"></i>AI Feedback</strong>
                    @if ($result->isEvaluated())
                        <span class="text-muted small ms-2">{{ $result->evaluated_at->diffForHumans() }}</span>
                    @endif
                </div>
                <div class="card-body ai-feedback">
                    @if ($result->isEvaluated())
                        {{ $result->ai_feedback }}
                    @else
                        <span class="text-muted">Your teacher has not generated AI feedback for this result yet.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
