@extends('layouts.app')

@section('title', 'Edit Student')
@section('heading', 'Edit ' . $student->name)

@section('actions')
    <a href="{{ route('teacher.students.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @include('partials._errors')

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.students.update', $student) }}">
                @csrf
                @method('PUT')
                @include('teacher.students._form', ['student' => $student])

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                    <a href="{{ route('teacher.students.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
