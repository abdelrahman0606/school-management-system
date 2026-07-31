{{-- Language switcher — rendered only when more than one language is active.
     $appLanguages / $appLanguage are shared by the SetLocale middleware.
     Used exclusively by the admin/staff/portal headers (the public site's
     own header has its own separate markup, see
     public/partials/header.blade.php) — so this always points at the
     BACKEND switch route ('language.switch.backend', writes the separate
     'backend_locale' session key), never the public one. Switching a
     backend user's own working language must never change what a public
     visitor sees, and vice versa — see SetLocale's own docblock. --}}
@if(($appLanguages ?? collect())->count() > 1)
  <div class="dropdown {{ $class ?? '' }}">
    <a class="dropdown-toggle text-decoration-none {{ $linkClass ?? '' }}" href="#" data-bs-toggle="dropdown" role="button">
      {{ $appLanguage?->flag }} {{ $appLanguage?->native_name ?? strtoupper(app()->getLocale()) }}
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
      @foreach($appLanguages as $lang)
        <li><a class="dropdown-item {{ $lang->code === app()->getLocale() ? 'active' : '' }}"
               href="{{ route('language.switch.backend', $lang->code) }}">{{ $lang->flag }} {{ $lang->native_name }}</a></li>
      @endforeach
    </ul>
  </div>
@endif
