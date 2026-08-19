@extends('layouts.app')

@section('title', 'My Results')
@section('heading', 'My Results')

@section('content')
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th class="text-end">Marks</th>
                    <th class="text-end">%</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">AI Feedback</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($results as $result)
                    <tr>
                        <td>{{ $result->exam_name }}</td>
                        <td>{{ $result->subject }}</td>
                        <td>{{ $result->exam_date?->toFormattedDateString() ?: '—' }}</td>
                        <td class="text-end">{{ $result->marks_obtained }} / {{ $result->full_marks }}</td>
                        <td class="text-end">{{ $result->percentage }}%</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $result->gradeVariant() }}">{{ $result->grade }}</span>
                        </td>
                        <td class="text-center">
                            @if ($result->isEvaluated())
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('student.results.show', $result) }}"
                               class="btn btn-sm btn-outline-secondary">Details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No results found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $results->links() }}</div>
@endsection
