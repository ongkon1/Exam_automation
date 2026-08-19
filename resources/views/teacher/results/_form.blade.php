@php($result = $result ?? null)
@php($selectedStudent = $selectedStudent ?? null)

<div class="row g-3">
    <div class="col-md-6">
        <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
        <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
            <option value="">— Select a student —</option>
            @foreach ($students as $student)
                <option value="{{ $student->id }}"
                    @selected(old('student_id', $result?->student_id ?? $selectedStudent) == $student->id)>
                    {{ $student->name }}{{ $student->roll_number ? " ({$student->roll_number})" : '' }}
                </option>
            @endforeach
        </select>
        @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="exam_name" class="form-label">Exam name <span class="text-danger">*</span></label>
        <input type="text" id="exam_name" name="exam_name" value="{{ old('exam_name', $result?->exam_name) }}"
               class="form-control @error('exam_name') is-invalid @enderror" placeholder="e.g. Midterm 2026" required>
        @error('exam_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
        <input type="text" id="subject" name="subject" value="{{ old('subject', $result?->subject) }}"
               class="form-control @error('subject') is-invalid @enderror" placeholder="e.g. Mathematics" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="exam_date" class="form-label">Exam date</label>
        <input type="date" id="exam_date" name="exam_date"
               value="{{ old('exam_date', $result?->exam_date?->toDateString()) }}"
               class="form-control @error('exam_date') is-invalid @enderror">
        @error('exam_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="full_marks" class="form-label">Full marks <span class="text-danger">*</span></label>
        <input type="number" id="full_marks" name="full_marks" min="1" max="1000"
               value="{{ old('full_marks', $result?->full_marks ?? 100) }}"
               class="form-control @error('full_marks') is-invalid @enderror" required>
        @error('full_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="marks_obtained" class="form-label">Marks obtained <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" id="marks_obtained" name="marks_obtained"
               value="{{ old('marks_obtained', $result?->marks_obtained) }}"
               class="form-control @error('marks_obtained') is-invalid @enderror" required>
        @error('marks_obtained')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">The letter grade is derived automatically from the percentage.</div>
    </div>

    <div class="col-12">
        <label for="remarks" class="form-label">Remarks</label>
        <textarea id="remarks" name="remarks" rows="3"
                  class="form-control @error('remarks') is-invalid @enderror"
                  placeholder="Optional note about the student's performance">{{ old('remarks', $result?->remarks) }}</textarea>
        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
