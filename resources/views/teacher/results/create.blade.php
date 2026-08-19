@extends('layouts.app')

@section('title', 'Add Result')
@section('heading', 'Add Result')

@section('actions')
    <a href="{{ route('teacher.results.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @include('partials._errors')

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.results.store') }}">
                @csrf
                @include('teacher.results._form', ['result' => null, 'selectedStudent' => $selectedStudent])

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Result
                    </button>
                    <a href="{{ route('teacher.results.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
