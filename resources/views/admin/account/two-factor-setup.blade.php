@extends('layouts.admin')
@section('title', __('Enable Two-Factor Authentication'))
@section('content')
  @include('admin.partials.page-header', ['title' => __('Enable Two-Factor Authentication'), 'crumbs' => [__('Account & Security'), __('Two-Factor Authentication')]])
  @include('partials.two-factor-setup')
@endsection
