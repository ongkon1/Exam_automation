@extends('layouts.app')

@section('title', 'Voice Test Result')
@section('heading', 'Voice Test Result')
@section('subheading', $transcript->created_at->format('F j, Y \a\t g:ia'))

@section('actions')
    <div class="d-flex gap-2">
        @if ($transcript->status !== \App\Models\ExamTranscript::STATUS_EVALUATED)
            <form method="POST" action="{{ route('teacher.transcripts.retry', $transcript) }}">
                @csrf
                <button class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry evaluation
                </button>
            </form>
        @endif
        @if ($transcript->result)
            <a href="{{ route('teacher.results.edit', $transcript->result) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Adjust result
            </a>
        @endif
        <a href="{{ route('teacher.transcripts.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
@endsection

@section('content')
    @if ($transcript->status === \App\Models\ExamTranscript::STATUS_UNMATCHED)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>
                No student has the phone number <strong>{{ $transcript->phone }}</strong>. Add it to the right
                student's profile — future calls from that number will be matched automatically.
            </div>
        </div>
    @endif

    {{-- The stored failure_reason is deliberately not surfaced here. It is still recorded
         on the row and reported by `php artisan voice-exam:retry` for diagnosis. --}}

    {{-- Who sat the test --}}
    <div class="card mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            @if ($transcript->student)
                <span class="avatar avatar-lg">{{ $transcript->student->initials() }}</span>
                <div class="flex-grow-1">
                    <a href="{{ route('teacher.students.show', $transcript->student) }}"
                       class="h5 mb-1 d-block text-decoration-none">{{ $transcript->student->name }}</a>
                    <div class="text-muted small">{{ $transcript->phone ?: '—' }}</div>
                </div>
            @else
                <span class="avatar avatar-lg avatar-muted"><i class="bi bi-question-lg"></i></span>
                <div class="flex-grow-1">
                    <div class="h5 mb-1 text-muted fst-italic">Unmatched call</div>
                    <div class="text-muted small">{{ $transcript->phone ?: '—' }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        {{-- Score --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Result</strong></div>
                <div class="card-body">
                    @if ($transcript->result)
                        @php($result = $transcript->result)
                        <div class="score-readout">
                            <div class="score-value">{{ $result->percentage }}<span>%</span></div>
                            <span class="pill pill--{{ $result->gradeVariant() }}">Grade {{ $result->grade }}</span>
                        </div>

                        <div class="meter meter-lg my-3">
                            <span style="width: {{ min($result->percentage, 100) }}%"></span>
                        </div>

                        <dl class="row mb-0 small">
                            <dt class="col-6">Marks</dt>
                            <dd class="col-6 text-end">
                                {{ $result->marks_obtained }} / {{ $result->full_marks }}
                            </dd>
                            <dt class="col-6">Evaluated</dt>
                            <dd class="col-6 text-end">
                                {{ $transcript->evaluated_at?->diffForHumans() ?? '—' }}
                            </dd>
                        </dl>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-hourglass-split fs-3 d-block mb-2 opacity-50"></i>
                            No result generated yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI feedback --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <strong><i class="bi bi-robot me-1"></i>AI Feedback</strong>
                </div>
                <div class="card-body">
                    @if ($transcript->result?->ai_feedback)
                        <div class="ai-feedback">{{ $transcript->result->ai_feedback }}</div>
                    @else
                        <p class="text-muted mb-0">No feedback has been generated for this test.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Provider summary --}}
        @if ($transcript->summary)
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <strong><i class="bi bi-card-text me-1"></i>Call Summary</strong>
                        <span class="text-muted small ms-2">written from the transcript</span>
                    </div>
                    <div class="card-body ai-feedback">{{ $transcript->summary }}</div>
                </div>
            </div>
        @endif

        {{-- Technical detail, tucked away --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <details>
                        <summary class="text-muted small">Call details</summary>
                        <dl class="row mb-0 small mt-3">
                            <dt class="col-sm-3">Call ID</dt>
                            <dd class="col-sm-9 text-break"><code>{{ $transcript->external_id ?: '—' }}</code></dd>
                            <dt class="col-sm-3">Phone</dt>
                            <dd class="col-sm-9">{{ $transcript->phone ?: '—' }}</dd>
                            <dt class="col-sm-3">Call outcome</dt>
                            <dd class="col-sm-9">
                                {{ $transcript->call_result ? ucfirst($transcript->call_result) : '—' }}
                            </dd>
                            <dt class="col-sm-3">Received</dt>
                            <dd class="col-sm-9">{{ $transcript->created_at->toDayDateTimeString() }}</dd>
                        </dl>
                    </details>
                </div>
            </div>
        </div>
    </div>
@endsection
