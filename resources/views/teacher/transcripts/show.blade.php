@extends('layouts.app')

@section('title', 'Transcript')
@section('heading', 'Voice Exam Transcript')

@section('actions')
    <a href="{{ route('teacher.transcripts.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @if ($transcript->status === \App\Models\ExamTranscript::STATUS_UNMATCHED)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No student has the phone number <strong>{{ $transcript->phone }}</strong>. Add it to the right
            student's profile — future calls from that number will be matched automatically.
        </div>
    @endif

    @if ($transcript->failure_reason)
        <div class="alert alert-danger">
            <i class="bi bi-x-circle me-1"></i>
            Evaluation failed: {{ $transcript->failure_reason }}
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Call</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Student</dt>
                        <dd class="col-7">{{ $transcript->student?->name ?? '—' }}</dd>
                        <dt class="col-5">Phone</dt><dd class="col-7">{{ $transcript->phone }}</dd>
                        <dt class="col-5">Subject</dt><dd class="col-7">{{ $transcript->subject }}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $transcript->statusVariant() }}">
                                {{ ucfirst($transcript->status) }}
                            </span>
                        </dd>
                        <dt class="col-5">Received</dt>
                        <dd class="col-7">{{ $transcript->created_at->toDayDateTimeString() }}</dd>
                        <dt class="col-5">Call ID</dt>
                        <dd class="col-7">{{ $transcript->external_id ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            @if ($transcript->result)
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><strong>Generated Result</strong></div>
                    <div class="card-body">
                        <dl class="row mb-2 small">
                            <dt class="col-5">Marks</dt>
                            <dd class="col-7">
                                {{ $transcript->result->marks_obtained }} / {{ $transcript->result->full_marks }}
                            </dd>
                            <dt class="col-5">Grade</dt>
                            <dd class="col-7">
                                <span class="badge bg-{{ $transcript->result->gradeVariant() }}">
                                    {{ $transcript->result->grade }}
                                </span>
                            </dd>
                        </dl>
                        <a href="{{ route('teacher.results.edit', $transcript->result) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Adjust result
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            @if ($transcript->result?->ai_feedback)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <strong><i class="bi bi-robot me-1"></i>AI Feedback</strong>
                    </div>
                    <div class="card-body ai-feedback">{{ $transcript->result->ai_feedback }}</div>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Transcript</strong></div>
                <div class="card-body ai-feedback">{{ $transcript->transcript }}</div>
            </div>
        </div>
    </div>
@endsection
