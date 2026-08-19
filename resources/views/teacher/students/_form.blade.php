@php($student = $student ?? null)

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $student?->name) }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" id="email" name="email" value="{{ old('email', $student?->email) }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="roll_number" class="form-label">Roll number</label>
        <input type="text" id="roll_number" name="roll_number" value="{{ old('roll_number', $student?->roll_number) }}"
               class="form-control @error('roll_number') is-invalid @enderror">
        @error('roll_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="class_name" class="form-label">Class</label>
        <input type="text" id="class_name" name="class_name" value="{{ old('class_name', $student?->class_name) }}"
               class="form-control @error('class_name') is-invalid @enderror">
        @error('class_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $student?->phone) }}"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="date_of_birth" class="form-label">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth"
               value="{{ old('date_of_birth', $student?->date_of_birth?->toDateString()) }}"
               class="form-control @error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" rows="2"
                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $student?->address) }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12"><hr class="my-1"></div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password @if ($student)<span class="text-muted small">(leave blank to keep current)</span>@else<span class="text-danger">*</span>@endif
        </label>
        <input type="password" id="password" name="password"
               class="form-control @error('password') is-invalid @enderror" @unless($student) required @endunless>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="form-control" @unless($student) required @endunless>
    </div>
</div>
