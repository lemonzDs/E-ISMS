<!doctype html>
<html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title', 'e-ISMS') · SUK Pahang</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body><a class="skip-link" href="#main">Langkau ke kandungan</a>
@auth
<div class="work-shell"><aside class="work-nav"><a href="{{ route('home') }}" class="brand">e-ISMS</a><small>SUK Pahang · Rintis tempatan</small><nav aria-label="Navigasi utama">
@if(auth()->user()->role !== 'admin')<a href="{{ route('documents.index') }}" @if(request()->is('documents*','versions*')) aria-current="page" @endif>Daftar dokumen</a>@endif
@can('manage-users')<a href="{{ route('admin.users') }}" @if(request()->is('admin/users*')) aria-current="page" @endif>Pengguna</a><a href="{{ route('admin.departments') }}" @if(request()->is('admin/departments*')) aria-current="page" @endif>Bahagian</a>@endcan
@can('viewAny',App\Models\Risk::class)<a href="{{ route('risks.index') }}" @if(request()->is('risks*')) aria-current="page" @endif>Daftar risiko</a>@endcan
<a href="{{ route('home') }}">Portal</a></nav></aside><div class="work-main"><header class="work-topbar"><span>{{ auth()->user()->department?->name }}<br><small class="muted">{{ ['admin'=>'Pentadbir','coordinator'=>'Penyelaras ISMS','officer'=>'Pegawai Bahagian','approver'=>'Pelulus'][auth()->user()->role] ?? '' }}</small></span><div class="work-actions"><span>{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="work-button secondary">Log keluar</button></form></div></header>
<main id="main" class="work-content" tabindex="-1">@include('components.feedback')@yield('content')</main><footer class="work-footer">Rintis pembangunan · Gunakan data contoh sahaja. Aturan kelulusan belum disahkan untuk operasi SUK.</footer></div></div>
@else<main id="main" tabindex="-1">@yield('content')</main>@endauth
</body></html>
