<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('asset/css/theme.css') }}" rel="stylesheet">
    <style>
        .sidebar { min-height: calc(100vh - 56px); }
    </style>
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ url('/') }}">
            <i class="bi bi-mortarboard-fill me-1"></i>{{ config('app.name') }}
        </a>
        @auth
            <div class="dropdown user-chip">
                <button class="btn dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <span class="d-none d-sm-inline fw-medium">{{ auth()->user()->name }}</span>
                    <span class="role-chip text-uppercase">{{ auth()->user()->role }}</span>
                    <span class="avatar avatar-sm">{{ auth()->user()->initials() }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li class="dropdown-header">
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted">{{ auth()->user()->email }}</div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @if (auth()->user()->isTeacher())
                        <li>
                            <a class="dropdown-item" href="{{ route('teacher.settings.edit') }}">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                    @else
                        <li>
                            <a class="dropdown-item" href="{{ route('student.profile') }}">
                                <i class="bi bi-person-badge me-2"></i>My Profile
                            </a>
                        </li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        @endauth
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        @auth
            <aside class="col-lg-2 col-md-3 sidebar p-3">
                <nav class="nav flex-column">
                    @if (auth()->user()->isTeacher())
                        <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}"
                           href="{{ route('teacher.dashboard') }}">
                            <i class="bi bi-grid-1x2 me-2"></i>Dashboard
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.students.*') ? 'active' : '' }}"
                           href="{{ route('teacher.students.index') }}">
                            <i class="bi bi-people me-2"></i>Students
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.results.*') ? 'active' : '' }}"
                           href="{{ route('teacher.results.index') }}">
                            <i class="bi bi-clipboard-data me-2"></i>Results
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.transcripts.*') ? 'active' : '' }}"
                           href="{{ route('teacher.transcripts.index') }}">
                            <i class="bi bi-mic me-2"></i>Voice Transcripts
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.webhooks.*') ? 'active' : '' }}"
                           href="{{ route('teacher.webhooks.index') }}">
                            <i class="bi bi-broadcast me-2"></i>Webhook Log
                        </a>
                        <a class="nav-link {{ request()->routeIs('teacher.settings.*') ? 'active' : '' }}"
                           href="{{ route('teacher.settings.edit') }}">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    @else
                        <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}"
                           href="{{ route('student.dashboard') }}">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <a class="nav-link {{ request()->routeIs('student.results.*') ? 'active' : '' }}"
                           href="{{ route('student.results.index') }}">
                            <i class="bi bi-journal-text me-2"></i>My Results
                        </a>
                        <a class="nav-link {{ request()->routeIs('student.voice-exam') ? 'active' : '' }}"
                           href="{{ route('student.voice-exam') }}">
                            <i class="bi bi-mic me-2"></i>Voice Exam
                        </a>
                        <a class="nav-link {{ request()->routeIs('student.profile', 'student.profile.*') ? 'active' : '' }}"
                           href="{{ route('student.profile') }}">
                            <i class="bi bi-person-badge me-2"></i>My Profile
                        </a>
                    @endif
                </nav>
            </aside>
        @endauth

        <main class="@auth col-lg-10 col-md-9 @else col-12 @endauth py-4 px-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
                <div>
                    <h1 class="h3 mb-1">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="text-muted mb-0">@yield('subheading')</p>
                    @endif
                </div>
                @yield('actions')
            </div>

            @include('partials._flash')

            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@stack('scripts')
</body>
</html>
