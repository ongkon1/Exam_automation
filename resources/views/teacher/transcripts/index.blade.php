@extends('layouts.app')

@section('title', 'Voice Test Results')
@section('heading', 'Voice Test Results')
@section('subheading', 'Spoken exams taken over the call system')

@section('actions')
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="status" class="form-select w-auto" onchange="this.form.submit()"
                aria-label="Filter by status">
            <option value="">All statuses</option>
            @foreach (['pending', 'evaluated', 'unmatched', 'failed'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
        @if ($status !== '')
            <a href="{{ route('teacher.transcripts.index') }}" class="btn btn-link px-1">Clear</a>
        @endif
    </form>
@endsection

@section('content')
    @if ($unmatchedCount > 0)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>
                <strong>{{ $unmatchedCount }} test{{ $unmatchedCount === 1 ? '' : 's' }} could not be matched
                    to a student by phone number.</strong>
                Check that the student's phone number on their profile matches the number they called from.
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Taken</th>
                    <th style="min-width: 180px;">Result</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($transcripts as $transcript)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($transcript->student)
                                    <span class="avatar">{{ $transcript->student->initials() }}</span>
                                    <div>
                                        <a href="{{ route('teacher.students.show', $transcript->student) }}"
                                           class="fw-semibold text-decoration-none">
                                            {{ $transcript->student->name }}
                                        </a>
                                        <div class="small text-muted">{{ $transcript->phone }}</div>
                                    </div>
                                @else
                                    <span class="avatar avatar-muted"><i class="bi bi-question-lg"></i></span>
                                    <div>
                                        <div class="fw-semibold text-muted fst-italic">Unmatched</div>
                                        <div class="small text-muted">{{ $transcript->phone ?: 'No number' }}</div>
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div>{{ $transcript->created_at->format('M j, Y') }}</div>
                            <div class="small text-muted">{{ $transcript->created_at->diffForHumans() }}</div>
                        </td>
                        <td>
                            @if ($transcript->result)
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pill pill--{{ $transcript->result->gradeVariant() }}">
                                        {{ $transcript->result->marks_obtained }} /
                                        {{ $transcript->result->full_marks }}
                                    </span>
                                    <div class="meter">
                                        <span style="width: {{ min($transcript->result->percentage, 100) }}%"></span>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('teacher.transcripts.show', $transcript) }}"
                               class="btn btn-sm btn-outline-secondary" aria-label="View this result">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-mic fs-3 d-block mb-2 opacity-50"></i>
                            No voice tests recorded yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transcripts->links() }}</div>
@endsection
