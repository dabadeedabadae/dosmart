@extends('admin.layouts.app')
@section('title', isset($institution) ? 'Редактировать учреждение' : 'Новое учреждение')

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="{{ route('admin.institutions.index') }}" class="icon-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <h1 class="page-title">{{ isset($institution) ? 'Редактировать учреждение' : 'Новое учреждение' }}</h1>
    </div>
</div>

<div class="card form-card">
    <div class="card-body" style="padding:28px">
        @include('admin.institutions._form')
    </div>
</div>
@endsection
