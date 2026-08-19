@extends('layouts.app')

@section('title', 'Add Student')
@section('heading', 'Add Student')

@section('actions')
    <a href="{{ route('teacher.students.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @include('partials._errors')

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.students.store') }}">
                @csrf
                @include('teacher.students._form', ['student' => null])

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Create Student
                    </button>
                    <a href="{{ route('teacher.students.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
