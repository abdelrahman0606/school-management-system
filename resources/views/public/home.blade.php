@extends('public.layout')
{{-- Block form + raw {!! !!}, not the inline @section('name', $value) form —
     see page.blade.php's comment for why: the inline form silently escapes
     $value via Laravel's own e(), and layout.blade.php's {{ $pageTitle }}
     escapes it again, so a site name containing &, ", <, or > would render
     double-escaped. --}}
@section('title')
{!! $settings->site_name ?? $school?->transOr('name') ?? 'Our School' !!}
@endsection
@section('content')
    <header class="hero py-5 py-lg-6">
        <div class="container py-4 py-lg-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <span class="eyebrow mb-3">{{ __('Welcome') }}</span>
                    <h1 class="display-4 mb-3 mt-2">{{ $settings->site_name ?? $school?->transOr('name') ?? 'Demo School' }}</h1>
                    <p class="lead mb-4 text-white-50" style="max-width:38rem;">
                        {{ $settings->transOr('meta_description') ?? 'Nurturing curious minds and building a community of lifelong learners.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#results" class="btn btn-light btn-lg px-4"><i class="bi bi-mortarboard"></i> {{ __('Check Results') }}</a>
                        <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg px-4">{{ __('Portal Login') }}</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-glass text-center p-4 h-100">
                                <div class="stat-num">{{ number_format($stats['active_students']) }}</div>
                                <div class="small mt-1" style="opacity:.85">{{ __('Students') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-glass text-center p-4 h-100">
                                <div class="stat-num">{{ number_format($stats['active_staff']) }}</div>
                                <div class="small mt-1" style="opacity:.85">{{ __('Teachers & Staff') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="notices" class="py-5 py-lg-6">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <span class="eyebrow">{{ __("What's New") }}</span>
                    <h2 class="section-title h3 mb-0 mt-1">{{ __('Latest Notices') }}</h2>
                </div>
                <a href="{{ url('/notices') }}" class="link-arrow text-brand">{{ __('View All') }} <i class="bi bi-arrow-right"></i></a>
            </div>
            @if ($notices->isEmpty())
                <p class="text-muted">{{ __('No Notices Published Right Now. Check Back Soon.') }}</p>
            @else
                <div class="row g-4">
                    @foreach ($notices->take(6) as $n)
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-center notice-icon mb-3">
                                        <i class="bi bi-megaphone-fill"></i>
                                    </div>
                                    <div class="small text-muted mb-1">{{ optional($n->publish_at ?? $n->created_at)->format('d M Y') }}</div>
                                    <h3 class="h6 fw-semibold">{{ $n->title }}</h3>
                                    <p class="text-muted small mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($n->body), 120) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="staff" class="py-5 py-lg-6 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <span class="eyebrow">{{ __('Meet The People') }}</span>
                <h2 class="section-title h3 mb-0 mt-1">{{ __('Our Team') }}</h2>
            </div>
            @if ($staff->isEmpty())
                <p class="text-muted text-center mb-0">{{ __('Staff Profiles Are Coming Soon.') }}</p>
            @else
                <div class="row g-4 text-center">
                    @foreach ($staff->take(8) as $member)
                        <div class="col-6 col-md-3">
                            <div class="rounded-circle bg-white avatar-ring d-inline-flex align-items-center justify-content-center mb-3"
                                style="width:88px;height:88px;">
                                @if ($member->photo)<img src="{{ $member->photo }}" class="rounded-circle"
                                    style="width:88px;height:88px;object-fit:cover;" alt="">
                                @else<span
                                class="text-brand fw-bold fs-3">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>@endif
                            </div>
                            <div class="fw-semibold">{{ $member->name }}</div>
                            <div class="text-muted small">{{ $member->designation?->name ?? __('Staff') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="results" class="py-5 py-lg-6">
        <div class="container">
            <div class="cta-panel p-5 p-lg-6 text-center">
                <h2 class="section-title h3 mb-2">{{ __('Check Your Exam Results') }}</h2>
                <p class="text-muted mb-4">{{ __('Results Are Published Here Once Released. Sign In To The Student Portal To View Full Report Cards.') }}</p>
                <a href="{{ route('login') }}" class="btn btn-brand btn-lg px-4 rounded-pill"><i
                        class="bi bi-box-arrow-in-right"></i> {{ __('Student Portal Login') }}</a>
            </div>
        </div>
    </section>
@endsection