@extends('layouts.app')

@section('title', 'Students')
@section('heading', 'Students')

@section('actions')
    <a href="{{ route('teacher.students.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Student
    </a>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-5">
                    <input type="text" name="search" value="{{ $search }}" class="form-control"
                           placeholder="Search by name, email or roll number">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button>
                </div>
                @if ($search !== '')
                    <div class="col-auto">
                        <a href="{{ route('teacher.students.index') }}" class="btn btn-link">Clear</a>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Roll No.</th>
                        <th>Class</th>
                        <th>Email</th>
                        <th class="text-center">Results</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->roll_number ?: '—' }}</td>
                            <td>{{ $student->class_name ?: '—' }}</td>
                            <td>{{ $student->email }}</td>
                            <td class="text-center">
                                <span class="badge bg-secondary">{{ $student->results_count }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('teacher.students.show', $student) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('teacher.students.edit', $student) }}"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('teacher.students.destroy', $student) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete {{ $student->name }} and all of their results?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No students found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $students->links() }}</div>
@endsection
