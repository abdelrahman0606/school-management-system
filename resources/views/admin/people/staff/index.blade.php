@extends('layouts.admin')
@section('title', __('Staff'))
@section('content')
  @include('admin.partials.page-header', [
    'title'  => __('Staff'),
    'crumbs' => [__('People'), __('Staff')],
    'action' => ['label' => __('Hire staff'), 'modal' => 'createModal'],
  ])

  <div class="card"><div class="card-body">
    <table class="table table-hover align-middle w-100 js-dt">
      <thead><tr><th>{{ __('Employee ID') }}</th><th>{{ __('Name') }}</th><th>{{ __('Designation') }}</th><th>{{ __('Department') }}</th>
        {{-- One column per active non-default language, header = short code
             (e.g. "BN") -- tick/cross shows whether this row's translatable
             fields are fully translated for that language. --}}
        @foreach ($contentLanguages as $lang)
          <th class="text-center" title="{{ $lang->native_name }}">{{ strtoupper($lang->code) }}</th>
        @endforeach
        <th class="text-end" data-orderable="false">{{ __('Actions') }}</th></tr></thead>
      <tbody>
        @foreach ($staff as $s)
          <tr>
            <td><code>{{ $s->employee_id }}</code></td>
            <td class="fw-semibold">{{ $s->name }}</td>
            <td>{{ $s->designation?->name ?? '—' }}</td>
            <td>{{ $s->department?->name ?? '—' }}</td>
            @foreach ($contentLanguages as $lang)
              <td class="text-center">
                @if ($s->isTranslated(['name'], $lang->code))
                  <i class="bi bi-check-lg text-success" aria-label="{{ __('Translated') }}"></i>
                @else
                  <i class="bi bi-x-lg text-muted" aria-label="{{ __('Not Translated') }}"></i>
                @endif
              </td>
            @endforeach
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal{{ $s->id }}">{{ __('Edit') }}</button>
              <form method="POST" action="{{ route('admin.staff.deactivate', $s->id) }}" class="d-inline" onsubmit="return confirm('Deactivate {{ $s->name }}?')">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-danger">{{ __('Deactivate') }}</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div></div>

  @php
    $selOptions = function ($list, $selected = null) {
      $out = '<option value="">— none —</option>';
      foreach ($list as $o) { $out .= '<option value="'.$o->id.'"'.(((int)$selected===(int)$o->id)?' selected':'').'>'.e($o->name).'</option>'; }
      return $out;
    };
    $genderOptions = function ($selected = null) {
      $out = '<option value="">—</option>';
      foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l) { $out .= '<option value="'.$v.'"'.($selected===$v?' selected':'').'>'.$l.'</option>'; }
      return $out;
    };
  @endphp

  @include('admin.people.staff._form', ['mode' => 'create'])
  @foreach ($staff as $s)
    @include('admin.people.staff._form', ['mode' => 'edit', 's' => $s])
  @endforeach
@endsection

@push('scripts')
  @include('admin.partials.translation-suggest-script')
@endpush
