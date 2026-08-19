@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Overview of your students and their results')

@section('actions')
    <form method="GET" class="d-flex align-items-center gap-2">
        @if ($class !== '')
            <input type="hidden" name="class" value="{{ $class }}">
        @endif
        <div class="range-picker">
            <i class="bi bi-calendar3"></i>
            <span>{{ $periodLabel }}</span>
            <select name="period" class="form-select" onchange="this.form.submit()" aria-label="Reporting period">
                @foreach ($periods as $days => $label)
                    <option value="{{ $days }}" @selected($period === $days)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>
@endsection

@section('content')
    {{-- Headline figures --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card stat-card stat-card--violet h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="flex-grow-1">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value">{{ number_format($students['total']) }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="trend trend--{{ $students['trend']['up'] ? 'up' : 'down' }}">
                                <i class="bi bi-arrow-{{ $students['trend']['up'] ? 'up' : 'down' }}"></i>
                                {{ $students['trend']['value'] }}%
                            </span>
                            <span class="text-muted small">vs previous period</span>
                        </div>
                    </div>
                    <svg class="sparkline d-none d-sm-block" viewBox="0 0 240 58" preserveAspectRatio="none"
                         role="img" aria-label="Student growth trend">
                        <defs>
                            <linearGradient id="spark-violet" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#6c63ff" stop-opacity=".28"/>
                                <stop offset="100%" stop-color="#6c63ff" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $students['spark']['area'] }}" fill="url(#spark-violet)"/>
                        <polyline points="{{ $students['spark']['line'] }}" fill="none" stroke="#6c63ff"
                                  stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card stat-card stat-card--green h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="bi bi-bar-chart-fill"></i></div>
                    <div class="flex-grow-1">
                        <div class="stat-label">Average Result</div>
                        <div class="stat-value">
                            {{ $average['total'] === null ? '—' : $average['total'].'%' }}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="trend trend--{{ $average['trend']['up'] ? 'up' : 'down' }}">
                                <i class="bi bi-arrow-{{ $average['trend']['up'] ? 'up' : 'down' }}"></i>
                                {{ $average['trend']['value'] }}%
                            </span>
                            <span class="text-muted small">vs previous period</span>
                        </div>
                    </div>
                    <svg class="sparkline d-none d-sm-block" viewBox="0 0 240 58" preserveAspectRatio="none"
                         role="img" aria-label="Average result trend">
                        <defs>
                            <linearGradient id="spark-green" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#22c55e" stop-opacity=".28"/>
                                <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $average['spark']['area'] }}" fill="url(#spark-green)"/>
                        <polyline points="{{ $average['spark']['line'] }}" fill="none" stroke="#22c55e"
                                  stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Distribution, two readings of the same data --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Results Overview</strong>
                    <form method="GET">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <select name="class" class="form-select form-select-sm w-auto" onchange="this.form.submit()"
                                aria-label="Filter by class">
                            <option value="">All Classes</option>
                            @foreach ($classes as $option)
                                <option value="{{ $option }}" @selected($class === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    @if ($gradedCount === 0)
                        <p class="text-muted text-center py-5 mb-0">No results recorded yet.</p>
                    @else
                        <div class="d-flex align-items-center gap-4 flex-wrap justify-content-center">
                            <div class="donut">
                                <svg viewBox="0 0 180 180" role="img" aria-label="Results distribution">
                                    <circle cx="90" cy="90" r="70" fill="none" stroke="var(--pia-surface-alt)"
                                            stroke-width="26"/>
                                    @foreach ($bands as $band)
                                        @if ($band['count'] > 0)
                                            <circle cx="90" cy="90" r="70" fill="none"
                                                    stroke="{{ $band['colour'] }}" stroke-width="26"
                                                    stroke-dasharray="{{ $band['dash'] }}"
                                                    stroke-dashoffset="{{ $band['offset'] }}"
                                                    transform="rotate(-90 90 90)"/>
                                        @endif
                                    @endforeach
                                </svg>
                                <div class="donut-centre">
                                    <div class="donut-value">{{ number_format($gradedCount) }}</div>
                                    <div class="donut-label">Students</div>
                                </div>
                            </div>

                            <ul class="legend flex-grow-1">
                                @foreach ($bands as $band)
                                    <li>
                                        <span class="legend-dot" style="background: {{ $band['colour'] }}"></span>
                                        <span class="legend-name">{{ $band['label'] }} ({{ $band['range'] }})</span>
                                        <span class="legend-value">
                                            {{ $band['count'] }} {{ Str::plural('Student', $band['count']) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="card-footer text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Each student is placed by their average across all recorded results.
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Results Distribution</strong>
                    <span class="chip">{{ $class === '' ? 'All Classes' : $class }}</span>
                </div>
                <div class="card-body">
                    @if ($gradedCount === 0)
                        <p class="text-muted text-center py-5 mb-0">No results recorded yet.</p>
                    @else
                        @php($peak = max(array_column($bands, 'count')) ?: 1)
                        <div class="bar-chart" style="--peak: {{ $peak }}">
                            <div class="bar-axis">
                                <span class="bar-axis-title">Students</span>
                                @foreach ([1, .75, .5, .25, 0] as $mark)
                                    <span class="bar-tick">{{ round($peak * $mark) }}</span>
                                @endforeach
                            </div>
                            <div class="bars">
                                @foreach ($bands as $band)
                                    <div class="bar-col">
                                        <div class="bar-count">{{ $band['count'] }}</div>
                                        <div class="bar"
                                             style="height: {{ max(round($band['count'] / $peak * 100, 1), 1) }}%;
                                                    background: {{ $band['colour'] }}"
                                             title="{{ $band['label'] }}: {{ $band['count'] }}"></div>
                                        <div class="bar-label">{{ $band['range'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-center text-muted small mt-2">Score Range</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent students --}}
    <div class="card">
        <div class="card-header"><strong>Recent Students</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Latest Result</th>
                    <th>Performance</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentStudents as $student)
                    @php($result = $student->latestResult)
                    @php($percentage = $result?->percentage)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar">{{ $student->initials() }}</span>
                                <div>
                                    <div class="fw-semibold">{{ $student->name }}</div>
                                    <div class="small text-muted">{{ $student->roll_number ?: '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->class_name ?: '—' }}</td>
                        <td>
                            @if ($result)
                                <span class="pill pill--{{ $result->gradeVariant() }}">{{ $percentage }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="min-width: 200px;">
                            @if ($result)
                                <div class="d-flex align-items-center gap-2">
                                    <div class="meter">
                                        <span style="width: {{ min($percentage, 100) }}%"></span>
                                    </div>
                                    <span class="small text-muted text-nowrap">
                                        {{ \App\Models\Result::gradeFor($percentage) === 'F' ? 'Needs work' : 'On track' }}
                                    </span>
                                </div>
                            @else
                                <span class="text-muted small">No results yet</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('teacher.students.show', $student) }}"
                               class="btn btn-sm btn-outline-secondary" aria-label="View {{ $student->name }}">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No students yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-center">
            <a href="{{ route('teacher.students.index') }}" class="fw-medium">View all students</a>
        </div>
    </div>
@endsection
