@extends('layouts.app')

@section('title', 'Voice Exam')
@section('heading', 'Voice Exam')

@push('styles')
    <style>
        /* Accent panel in the site palette; the widget inside blends into it. */
        .voice-exam-card {
            background: linear-gradient(135deg, var(--pia-accent-2) 0%, var(--pia-violet) 55%, var(--pia-accent) 160%);
            background-color: var(--pia-surface-alt);
            border: none;
            border-radius: var(--pia-radius);
            box-shadow: 0 0 28px rgba(108, 99, 255, 0.35);
            color: #fff;
        }
        .voice-exam-card .card-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
        }
        .voice-exam-card dt,
        .voice-exam-card .form-label,
        .voice-exam-card .form-text {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .voice-exam-card dd { color: #fff; }
        .voice-exam-card a {
            color: #fff;
            text-decoration: underline;
        }
        .voice-exam-card .form-select {
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: var(--pia-radius-sm);
            box-shadow: none;
            color: #23244a;
        }

        /* Anchor for the widget's absolutely-positioned volume controller. */
        #webcall-widget { position: relative; }

        /* Lift the vendor popup out of its fixed corner and into the card. Its own
           gradient is dropped so it blends into the card rather than stacking on it. */
        #spcl-popup.webcall-embedded {
            position: static !important;
            right: auto !important;
            bottom: auto !important;
            width: 100% !important;
            max-width: 100% !important;
            background: transparent !important;
            background-color: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 44px 0 0 0 !important;
        }

        /* The floating launcher is replaced by the embedded panel. */
        #spcl-toggle { display: none !important; }

        /* The country column is gone, so the number field takes the full width. */
        #spcl-popup.webcall-embedded .spcl-contact-number-box {
            grid-template-columns: 1fr !important;
            padding: 0 !important;
        }

        #spcl-popup.webcall-embedded .spcl-contact-input[readonly] {
            background-color: #f1f3f5;
            cursor: not-allowed;
        }

        /* Match the loading state and the no-phone notice to the panel. */
        .voice-exam-card #webcall-placeholder {
            background-color: rgba(255, 255, 255, 0.12) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: rgba(255, 255, 255, 0.85) !important;
        }
        .voice-exam-card .alert-warning {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.35);
            color: #fff;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('asset/js/webcall-bd%201.js') }}?v=1.0.0" defer></script>
    <script src="{{ asset('asset/js/voice-exam-embed.js') }}?v=1.0.0" defer></script>
@endpush

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm voice-exam-card">
                <div class="card-header"><strong>Start an Exam</strong></div>
                <div class="card-body">
                    <dl class="row mb-3 small">
                        <dt class="col-4 text-muted">Name</dt>
                        <dd class="col-8 mb-1">{{ $student->name }}</dd>
                        <dt class="col-4 text-muted">Phone</dt>
                        <dd class="col-8 mb-0">{{ $student->phone ?: '—' }}</dd>
                    </dl>

                    <p class="form-text mt-0">
                        The call uses the name and number on your profile — your result is matched back to
                        you by that number.
                        <a href="{{ route('student.profile.edit') }}">Update profile</a>
                    </p>

                    <div class="mb-3">
                        <label for="webcall-subject" class="form-label">Subject</label>
                        <select id="webcall-subject" class="form-select">
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject }}">{{ $subject }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- The Speaklar widget is relocated into this container by voice-exam-embed.js. --}}
                    <div id="webcall-widget"
                         data-student-id="{{ $student->id }}"
                         data-name="{{ $student->name }}"
                         data-phone="{{ $student->phone }}"
                         data-email="{{ $student->email }}"
                         data-website="{{ $widgetWebsite }}"
                         data-subject-select="#webcall-subject"
                         data-session-url="{{ route('student.voice-exam.sessions.store') }}"
                         data-session-end-url="{{ route('student.voice-exam.sessions.end') }}">
                        @if (blank($student->phone))
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                You have no phone number saved, so a voice exam cannot be matched back to
                                you — <a href="{{ route('student.profile.edit') }}" class="alert-link">add one first</a>.
                            </div>
                        @else
                            <div id="webcall-placeholder" class="border rounded p-4 text-center bg-light text-muted small">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Loading the voice call widget…
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>My Voice Exams</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Taken</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Result</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($transcripts as $transcript)
                            <tr>
                                <td>{{ $transcript->subject }}</td>
                                <td>{{ $transcript->created_at->diffForHumans() }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $transcript->statusVariant() }}">
                                        {{ ucfirst($transcript->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($transcript->result)
                                        <a href="{{ route('student.results.show', $transcript->result) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            {{ $transcript->result->marks_obtained }} /
                                            {{ $transcript->result->full_marks }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    You have not taken a voice exam yet.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">{{ $transcripts->links() }}</div>
        </div>
    </div>
@endsection
