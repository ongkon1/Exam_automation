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
        .voice-exam-card .form-label,
        .voice-exam-card .form-text {
            color: rgba(255, 255, 255, 0.8) !important;
        }
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

        /* The avatar stands alone at the top of the panel; the identity fields below it
           are filled from the profile and hidden. */
        .webcall-identity {
            align-items: center;
            display: flex;
            justify-content: center;
            padding: 8px 0 20px;
        }

        .webcall-identity #avatar-container {
            flex: 0 0 auto;
            height: 120px !important;
            margin: 0 !important;
            width: 120px !important;
        }

        .webcall-identity #spcl-avatar {
            height: 120px !important;
            margin: 0 !important;
            width: 120px !important;
        }

        /* Live camera, shown in place of the student's details during a call. */
        .webcall-camera {
            border-radius: var(--pia-radius-sm);
            overflow: hidden;
            position: relative;
        }

        .webcall-camera video {
            background: #000;
            display: block;
            height: auto;
            width: 100%;
            /* Mirror it, the way people expect to see themselves. */
            transform: scaleX(-1);
        }

        /* The avatar moves onto the video while the call runs, inset bottom-right.
           The vendor sets `position: relative` inline on the container, so every
           positioning declaration here has to be !important to outrank it. */
        .webcall-camera #avatar-container {
            bottom: 14px !important;
            height: 88px !important;
            left: auto !important;
            margin: 0 !important;
            position: absolute !important;
            right: 14px !important;
            top: auto !important;
            width: 88px !important;
            z-index: 2;
        }

        .webcall-camera #spcl-avatar {
            border: 2px solid rgba(255, 255, 255, 0.85);
            border-radius: 50%;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
            height: 88px !important;
            margin: 0 !important;
            width: 88px !important;
        }

        /* The vendor's speaking rings are drawn around a 120px avatar, so at this size
           they spill past the video edge and get clipped. */
        .webcall-camera .spcl-audio-ring {
            display: none !important;
        }

        .webcall-camera-label {
            align-items: center;
            background: rgba(0, 0, 0, 0.55);
            border-radius: 999px;
            color: #fff;
            display: flex;
            font-size: 0.75rem;
            gap: 6px;
            left: 10px;
            padding: 4px 10px;
            position: absolute;
            top: 10px;
        }

        .webcall-camera-label::before {
            background: #ef4444;
            border-radius: 50%;
            content: "";
            height: 8px;
            width: 8px;
            animation: webcall-rec 1.4s ease-in-out infinite;
        }

        @keyframes webcall-rec {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.25; }
        }

        @media (prefers-reduced-motion: reduce) {
            .webcall-camera-label::before { animation: none; }
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
                    {{-- The Speaklar widget is relocated into this container by voice-exam-embed.js.
                         The name and number it needs come from these data attributes, so they are
                         no longer printed on the card. --}}
                    <div id="webcall-widget"
                         data-student-id="{{ $student->id }}"
                         data-name="{{ $student->name }}"
                         data-phone="{{ $student->phone }}"
                         data-email="{{ $student->email }}"
                         data-website="{{ $widgetWebsite }}"
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
                                <td>{{ $transcript->subject ?: config('webcall.subject') }}</td>
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
