@extends('layouts.app')

@section('title', 'Edit Result')
@section('heading', 'Edit Result')

@section('actions')
    <a href="{{ route('teacher.results.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @include('partials._errors')

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.results.update', $result) }}">
                @csrf
                @method('PUT')
                @include('teacher.results._form', ['result' => $result, 'selectedStudent' => null])

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                    <a href="{{ route('teacher.results.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @if ($result->isEvaluated())
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white">
                <strong><i class="bi bi-robot me-1"></i>AI Evaluation</strong>
                <span class="text-muted small ms-2">generated {{ $result->evaluated_at->diffForHumans() }}</span>
            </div>
            <div class="card-body ai-feedback">{{ $result->ai_feedback }}</div>
        </div>
    @endif
@endsection
