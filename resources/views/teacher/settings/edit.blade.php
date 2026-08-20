@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')

@section('content')
    @include('partials._errors')

    <form method="POST" action="{{ route('teacher.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><strong>My Information</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $teacher->name) }}"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email', $teacher->email) }}"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $teacher->phone) }}"
                               class="form-control @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="password" class="form-label">
                            New password <span class="text-muted small">(optional)</span>
                        </label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="password_confirmation" class="form-label">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white">
                <strong>AI Evaluation Prompts</strong>
                <div class="text-muted small">
                    Both fields must be filled in before the <em>AI Evaluate</em> button becomes available on the
                    Results page.
                </div>

                <div class="mt-2">
                    @if ($openAiConfigured)
                        <span class="badge bg-success">OpenAI key configured</span>
                    @else
                        <span class="badge bg-danger">OpenAI key missing</span>
                        <div class="text-muted small mt-1">
                            Evaluations will fail on this server until <code>OPENAI_API_KEY</code> is set in its
                            <code>.env</code> file. After setting it run <code>php artisan config:clear</code> —
                            a cached config keeps serving the old, empty value.
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="system_prompt" class="form-label">System prompt</label>
                    <textarea id="system_prompt" name="system_prompt" rows="5"
                              class="form-control @error('system_prompt') is-invalid @enderror"
                              placeholder="e.g. You are an experienced examiner writing concise, encouraging feedback for secondary school students.">{{ old('system_prompt', $settings->system_prompt) }}</textarea>
                    @error('system_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Sets the role and tone the model adopts.</div>
                </div>

                <div class="mb-0">
                    <label for="evaluation_prompt" class="form-label">Evaluation prompt</label>
                    <textarea id="evaluation_prompt" name="evaluation_prompt" rows="5"
                              class="form-control @error('evaluation_prompt') is-invalid @enderror"
                              placeholder="e.g. Review the exam result below and give the student three specific suggestions for improvement.">{{ old('evaluation_prompt', $settings->evaluation_prompt) }}</textarea>
                    @error('evaluation_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Sent alongside each student's result data, and used as the system prompt when a
                        voice test transcript is summarised.
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Settings
        </button>
    </form>
@endsection
