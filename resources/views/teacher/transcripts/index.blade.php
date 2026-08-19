@extends('layouts.app')

@section('title', 'Voice Transcripts')
@section('heading', 'Voice Exam Transcripts')

@section('content')
    @if ($unmatchedCount > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $unmatchedCount }} transcript{{ $unmatchedCount === 1 ? '' : 's' }} could not be matched to a
            student by phone number. Check that the student's phone number on their profile matches the number
            they called from.
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (['pending', 'evaluated', 'unmatched', 'failed'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
                </div>
                @if ($status !== '')
                    <div class="col-auto">
                        <a href="{{ route('teacher.transcripts.index') }}" class="btn btn-link">Clear</a>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Phone</th>
                        <th>Subject</th>
                        <th>Received</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Result</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($transcripts as $transcript)
                        <tr>
                            <td>
                                @if ($transcript->student)
                                    <a href="{{ route('teacher.students.show', $transcript->student) }}"
                                       class="text-decoration-none">{{ $transcript->student->name }}</a>
                                @else
                                    <span class="text-muted fst-italic">Unmatched</span>
                                @endif
                            </td>
                            <td>{{ $transcript->phone }}</td>
                            <td>{{ $transcript->subject ?: config('webcall.subject') }}</td>
                            <td>{{ $transcript->created_at->diffForHumans() }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $transcript->statusVariant() }}">
                                    {{ ucfirst($transcript->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($transcript->result)
                                    {{ $transcript->result->marks_obtained }} / {{ $transcript->result->full_marks }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('teacher.transcripts.show', $transcript) }}"
                                   class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No transcripts received yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $transcripts->links() }}</div>
@endsection
