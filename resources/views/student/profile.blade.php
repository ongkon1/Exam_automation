@extends('layouts.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')

@section('actions')
    <a href="{{ route('student.profile.edit') }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i>Edit Profile
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Personal Information</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Full name</dt><dd class="col-sm-8">{{ $student->name }}</dd>
                        <dt class="col-sm-4">Roll number</dt><dd class="col-sm-8">{{ $student->roll_number ?: '—' }}</dd>
                        <dt class="col-sm-4">Class</dt><dd class="col-sm-8">{{ $student->class_name ?: '—' }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $student->email }}</dd>
                        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $student->phone ?: '—' }}</dd>
                        <dt class="col-sm-4">Date of birth</dt>
                        <dd class="col-sm-8">{{ $student->date_of_birth?->toFormattedDateString() ?: '—' }}</dd>
                        <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $student->address ?: '—' }}</dd>
                        <dt class="col-sm-4">Registered on</dt>
                        <dd class="col-sm-8">{{ $student->created_at->toFormattedDateString() }}</dd>
                    </dl>
                </div>
                <div class="card-footer bg-white text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    You can update your own contact details. Your roll number and class are set by your
                    teacher — contact them if either needs correcting.
                </div>
            </div>
        </div>
    </div>
@endsection
