@extends('layouts.app')
@section('title','Log masuk')
@section('content')
<div class="auth-wrap"><a href="{{ route('home') }}">← Portal e-ISMS</a><h1>Log masuk ke ruang kerja</h1><p class="muted">Akaun dalaman SUK Pahang · Rintis tempatan</p>@include('components.feedback')<div class="work-panel"><form method="post" action="{{ route('login') }}">@csrf<div class="work-field"><label for="email">E-mel</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus @error('email') aria-invalid="true" @enderror></div><div class="work-field"><label for="password">Kata laluan</label><input id="password" name="password" type="password" required autocomplete="current-password"></div><button class="work-button">Log masuk</button></form></div><p class="muted">Hubungi pentadbir jika akaun belum tersedia atau perlu menetapkan semula kata laluan.</p></div>
@endsection
