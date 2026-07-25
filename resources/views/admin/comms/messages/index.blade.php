@extends('layouts.admin')
@section('title', __('Messages'))
@section('content')
  @include('admin.partials.page-header', [
    'title' => __('Messages'),
    'crumbs' => [__('Comms'), __('Messages')],
    'actions' => [
      ['label' => __('All Conversations'), 'url' => route('admin.messages.all'), 'icon' => 'bi-eye', 'variant' => 'outline-secondary'],
      ['label' => __('Compose'), 'modal' => 'composeModal', 'icon' => 'bi-pencil-square'],
    ],
  ])

  <div class="card"><div class="card-body">
    @if ($threads->isEmpty())
      <p class="text-muted text-center py-4 mb-0">{{ __('Your Inbox Is Empty. Start A Conversation With Compose.') }}</p>
    @else
      <table class="table table-hover align-middle w-100 js-dt">
        <thead><tr><th>{{ __('Conversation') }}</th><th>{{ __('Type') }}</th><th>{{ __('Last Activity') }}</th><th class="text-end">{{ __('Unread') }}</th><th data-orderable="false"></th></tr></thead>
        <tbody>
          @foreach ($threads as $t)
            @php
              $others = $t->participants->pluck('user_id')->reject(fn ($id) => $id === auth()->id())
                  ->map(fn ($id) => $userMap[$id] ?? 'User #'.$id)->filter()->values();
              $title = $t->subject ?: $others->join(', ') ?: 'Conversation';
            @endphp
            <tr>
              <td>
                <a href="{{ route('admin.messages.show', $t->id) }}" class="fw-semibold text-decoration-none">{{ $title }}</a>
                @if ($t->is_locked)<i class="bi bi-lock-fill text-muted" title="{{ __('Locked') }}"></i>@endif
              </td>
              <td><x-badge variant="neutral">{{ ucfirst($t->type) }}</x-badge></td>
              <td data-order="{{ optional($t->last_message_at)->timestamp ?? 0 }}">{{ $t->last_message_at?->diffForHumans() ?? '—' }}</td>
              <td class="text-end">@if (($t->unread_count ?? 0) > 0)<x-badge variant="primary">{{ $t->unread_count }}</x-badge>@else <span class="text-muted">0</span>@endif</td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.messages.show', $t->id) }}">{{ __('Open') }}</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div></div>

  <div class="modal fade" id="composeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('admin.messages.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">{{ __('New Conversation') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">To <span class="text-danger">*</span></label>
          <select name="participant_ids[]" class="form-select js-select" multiple required>
            @foreach ($users as $u)
              <option value="{{ $u['id'] }}">{{ $u['label'] }}</option>
            @endforeach
          </select>
          <div class="form-text">{{ __('Pick One Person For A Direct Chat, Or Several For A Group.') }}</div></div>
        <div class="mb-3"><label class="form-label">{{ __('Subject') }} <span class="text-muted small">(groups only)</span></label>
          <input name="subject" class="form-control" value="{{ old('subject') }}"></div>
        <div class="mb-0"><label class="form-label">{{ __('Message') }} <span class="text-danger">*</span></label>
          <textarea name="body" class="form-control" rows="4" required>{{ old('body') }}</textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Send') }}</button></div>
    </form>
  </div></div></div>
@endsection
