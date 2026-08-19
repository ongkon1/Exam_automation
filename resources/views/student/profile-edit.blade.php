@extends('layouts.app')

@section('title', 'Edit Profile')
@section('heading', 'Edit My Profile')

@section('actions')
    <a href="{{ route('student.profile') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    @include('partials._errors')

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('student.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>My Information</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $student->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" value="{{ old('email', $student->email) }}"
                                       class="form-control @error('email') is-invalid @enderror" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">This is also the address you sign in with.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Roll number</label>
                                <input type="text" class="form-control" value="{{ $student->roll_number ?: '—' }}" disabled>
                                <div class="form-text">Set by your teacher.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <input type="text" class="form-control" value="{{ $student->class_name ?: '—' }}" disabled>
                                <div class="form-text">Set by your teacher.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone', $student->phone) }}"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">Date of birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth"
                                       value="{{ old('date_of_birth', $student->date_of_birth?->toDateString()) }}"
                                       class="form-control @error('date_of_birth') is-invalid @enderror">
                                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" rows="2"
                                          class="form-control @error('address') is-invalid @enderror">{{ old('address', $student->address) }}</textarea>
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <strong>Change Password</strong>
                        <div class="text-muted small">Leave both fields blank to keep your current password.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">New password</label>
                                <input type="password" id="password" name="password"
                                       class="form-control @error('password') is-invalid @enderror">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirm new password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
                <a href="{{ route('student.profile') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
