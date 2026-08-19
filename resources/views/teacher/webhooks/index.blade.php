@extends('layouts.app')

@section('title', 'Webhook Log')
@section('heading', 'Webhook Log')

@section('actions')
    @if ($requests->total() > 0)
        <form method="POST" action="{{ route('teacher.webhooks.destroy') }}"
              onsubmit="return confirm('Clear the entire webhook log?');">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Clear log</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <p class="mb-2">
                Every request that reaches the callback endpoint is recorded here — including ones rejected
                for a bad secret or a malformed payload.
            </p>
            <dl class="row mb-0 small">
                <dt class="col-sm-3">Callback URL</dt>
                <dd class="col-sm-9"><code>{{ $endpoint }}</code></dd>
                <dt class="col-sm-3">Method</dt>
                <dd class="col-sm-9"><code>POST</code> with a JSON body</dd>
                <dt class="col-sm-3">Access</dt>
                <dd class="col-sm-9">
                    @if ($secretConfigured)
                        <span class="badge bg-success">Protected</span>
                        — callers must send
                        <code>X-Webhook-Secret: &lt;WEBCALL_WEBHOOK_SECRET&gt;</code>.
                    @else
                        <span class="badge bg-warning text-dark">Open</span>
                        — no header or key required. Anyone who knows this URL can post a transcript.
                        Set <code>WEBCALL_WEBHOOK_SECRET</code> in <code>.env</code> to require a header.
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    @if ($requests->total() === 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Nothing has reached this endpoint yet.</strong>
            If the provider says it is calling, check the URL above is exactly what they have configured and
            that it is reachable from the internet — a <code>localhost</code> or <code>127.0.0.1</code>
            address cannot be called from outside this machine.
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Received</th>
                    <th>Method</th>
                    <th>From</th>
                    <th class="text-center">Status</th>
                    <th>Outcome</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($requests as $entry)
                    <tr>
                        <td>{{ $entry->created_at->toDayDateTimeString() }}</td>
                        <td><code>{{ $entry->method }}</code></td>
                        <td>{{ $entry->ip ?: '—' }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $entry->statusVariant() }}">
                                {{ $entry->status_code ?? '—' }}
                            </span>
                        </td>
                        <td class="small">{{ $entry->outcome() }}</td>
                        <td class="text-end">
                            <a href="{{ route('teacher.webhooks.show', $entry) }}"
                               class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No requests recorded yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
