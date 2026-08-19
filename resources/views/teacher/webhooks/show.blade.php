@extends('layouts.app')

@section('title', 'Webhook Request')
@section('heading', 'Webhook Request')

@section('actions')
    <a href="{{ route('teacher.webhooks.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    <div class="alert alert-{{ $request->statusVariant() }}">
        <strong>HTTP {{ $request->status_code ?? '—' }}</strong> — {{ $request->outcome() }}
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Request</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">Received</dt>
                        <dd class="col-8">{{ $request->created_at->toDayDateTimeString() }}</dd>
                        <dt class="col-4">Method</dt><dd class="col-8"><code>{{ $request->method }}</code></dd>
                        <dt class="col-4">URL</dt><dd class="col-8 text-break">{{ $request->path }}</dd>
                        <dt class="col-4">From IP</dt><dd class="col-8">{{ $request->ip ?: '—' }}</dd>
                        <dt class="col-4">Content type</dt>
                        <dd class="col-8">{{ $request->content_type ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <strong>Headers</strong>
                    <div class="text-muted small">Secret and authorization values are masked.</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <tbody>
                        @forelse ($request->headers ?? [] as $name => $value)
                            <tr>
                                <td class="text-nowrap"><code>{{ $name }}</code></td>
                                <td class="text-break">{{ $value }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No headers recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Body</strong></div>
                <div class="card-body">
                    <pre class="ai-feedback mb-0 small">{{ $request->body ?: '(empty)' }}</pre>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Our response</strong></div>
                <div class="card-body">
                    <pre class="ai-feedback mb-0 small">{{ $request->response ?: '(none recorded)' }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
