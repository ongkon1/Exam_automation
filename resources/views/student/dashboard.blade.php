@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Welcome, ' . $student->name)

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Results</div>
                    <div class="fs-3 fw-semibold">{{ $totalResults }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Average</div>
                    <div class="fs-3 fw-semibold">
                        {{ $averagePercentage !== null ? $averagePercentage . '%' : '—' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Best Subject</div>
                    <div class="fs-5 fw-semibold">{{ $bestResult?->subject ?? '—' }}</div>
                    @if ($bestResult)
                        <div class="text-success small">{{ $bestResult->percentage }}%</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Needs Work</div>
                    <div class="fs-5 fw-semibold">{{ $worstResult?->subject ?? '—' }}</div>
                    @if ($worstResult)
                        <div class="text-danger small">{{ $worstResult->percentage }}%</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Recent Results</strong>
            <a href="{{ route('student.results.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
        </div>
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
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentResults as $result)
                    <tr>
                        <td>{{ $result->exam_name }}</td>
                        <td>{{ $result->subject }}</td>
                        <td>{{ $result->exam_date?->toFormattedDateString() ?: '—' }}</td>
                        <td class="text-end">{{ $result->marks_obtained }} / {{ $result->full_marks }}</td>
                        <td class="text-end">{{ $result->percentage }}%</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $result->gradeVariant() }}">{{ $result->grade }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('student.results.show', $result) }}"
                               class="btn btn-sm btn-outline-secondary">Details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No results have been published for you yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
